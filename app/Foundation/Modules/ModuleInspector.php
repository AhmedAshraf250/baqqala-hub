<?php

namespace App\Foundation\Modules;

use App\Foundation\Contracts\Modules\DependsOnModulesInterface;
use App\Foundation\Contracts\Modules\UninstallableInterface;
use App\Foundation\Contracts\Modules\UninstallStepInterface;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Translation\Translator;

/**
 * Every module in the codebase, enabled or not, and what of each is stored.
 *
 * {@see ModuleRegistry} is what runs, and never touches the disk. This is its
 * console counterpart: a disabled module is not registered, so the only way to
 * find it is to look in app/Modules, and the only way to know what it left is
 * to ask the database. The module commands and `php artisan about` read it;
 * nothing on a request does.
 */
final readonly class ModuleInspector
{
    public function __construct(
        private Application $app,
        private Config $config,
        private Migrator $migrator,
        private ModuleRegistry $registry,
        private Translator $translator,
    ) {}

    /**
     * Every module: the enabled ones in the order `config/modules.php` lists
     * them, then the others by key.
     *
     * @return list<ModuleServiceProvider>
     */
    public function all(): array
    {
        $enabled = array_map($this->instance(...), $this->enabledClasses());

        $others = array_map(
            $this->instance(...),
            array_values(array_diff($this->classesOnDisk(), $this->enabledClasses())),
        );

        usort($others, static fn (ModuleServiceProvider $a, ModuleServiceProvider $b): int => $a->key() <=> $b->key());

        return [...$enabled, ...$others];
    }

    /**
     * A module by its key or its provider class, or null if there is none.
     */
    public function find(string $module): ?ModuleServiceProvider
    {
        if (is_subclass_of($module, ModuleServiceProvider::class)) {
            return $this->instance($module);
        }

        foreach ($this->all() as $candidate) {
            if ($candidate->key() === $module) {
                return $candidate;
            }
        }

        return null;
    }

    public function isEnabled(ModuleServiceProvider $module): bool
    {
        return in_array($module::class, $this->enabledClasses(), true);
    }

    /**
     * The modules that run, in the order `config/modules.php` lists them.
     *
     * @return list<ModuleServiceProvider>
     */
    public function enabled(): array
    {
        return array_map($this->instance(...), $this->enabledClasses());
    }

    /**
     * The keys of the modules in the codebase that do not run. What is stored
     * for them stays by design, so it is never "out of sync".
     *
     * @return list<string>
     */
    public function disabledKeys(): array
    {
        return array_values(array_map(
            static fn (ModuleServiceProvider $module): string => $module->key(),
            array_filter($this->all(), fn (ModuleServiceProvider $module): bool => ! $this->isEnabled($module)),
        ));
    }

    public function state(ModuleServiceProvider $module): ModuleState
    {
        return match (true) {
            $this->isEnabled($module) => ModuleState::Enabled,
            $this->footprint($module)->isEmpty() => ModuleState::NotInstalled,
            default => ModuleState::Disabled,
        };
    }

    /**
     * The modules that declare they cannot work without this one.
     *
     * @return list<ModuleServiceProvider>
     */
    public function dependents(ModuleServiceProvider $module): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (ModuleServiceProvider $other): bool => $other instanceof DependsOnModulesInterface
                && in_array($module->key(), $other->dependsOn(), true),
        ));
    }

    /**
     * The module's migrations that have run, newest first.
     *
     * @return list<string>
     */
    public function ranMigrations(ModuleServiceProvider $module): array
    {
        return array_values(array_reverse(array_intersect($this->migrationsOf($module), $this->ran())));
    }

    /**
     * The module's migrations that have not run yet, oldest first.
     *
     * @return list<string>
     */
    public function pendingMigrations(ModuleServiceProvider $module): array
    {
        return array_values(array_diff($this->migrationsOf($module), $this->ran()));
    }

    /**
     * What of the module is stored right now.
     */
    public function footprint(ModuleServiceProvider $module): ModuleFootprint
    {
        $migrations = $this->ranMigrations($module);

        return new ModuleFootprint(
            tables: $this->tablesCreatedBy($module, $migrations),
            migrations: $migrations,
            held: array_merge(...array_map(
                static fn (UninstallStepInterface $step): array => $step->describe($module),
                $this->steps(),
            )),
            leftovers: $this->leftovers($module, $migrations),
        );
    }

    /**
     * What the module says it keeps outside its tables.
     *
     * It may find that through its tables — Customers finds the logins it
     * gave out through `customers.user_id` — so it is asked only while they
     * exist. Once they are dropped its leftovers have already been proven
     * gone, right after its own `uninstall()`.
     *
     * @param  list<string>  $ranMigrations
     * @return list<string>
     */
    public function leftovers(ModuleServiceProvider $module, ?array $ranMigrations = null): array
    {
        if (! $module instanceof UninstallableInterface) {
            return [];
        }

        $tablesAreThere = ($ranMigrations ?? $this->ranMigrations($module)) !== [] || $this->migrationsOf($module) === [];

        return $tablesAreThere ? $module->leftovers() : [];
    }

    /**
     * What the layers hold for modules, each able to describe and remove its
     * part — tagged by the layer that holds it.
     *
     * @return list<UninstallStepInterface>
     */
    public function steps(): array
    {
        $steps = [];

        foreach ($this->app->tagged(UninstallStepInterface::class) as $step) {
            if ($step instanceof UninstallStepInterface) {
                $steps[] = $step;
            }
        }

        return $steps;
    }

    /**
     * The running instance of an enabled module; for any other, a new one
     * with its configuration and strings loaded, since a disabled module is
     * not registered and its cleanup, or its name, may need them.
     *
     * @param  class-string<ModuleServiceProvider>  $class
     */
    private function instance(string $class): ModuleServiceProvider
    {
        $running = $this->registry->all()->first(static fn (ModuleServiceProvider $module): bool => $module::class === $class);

        if ($running !== null) {
            return $running;
        }

        $module = new $class($this->app);
        $file = $module->configFile();

        if ($file !== null && ! $this->config->has($module->key())) {
            $this->config->set($module->key(), require $file);
        }

        $this->translator->addNamespace($module->key(), $module->path('Resources/lang'));

        return $module;
    }

    /**
     * @return list<class-string<ModuleServiceProvider>>
     */
    private function enabledClasses(): array
    {
        return array_values(array_filter(
            (array) $this->config->get('modules.enabled', []),
            static fn (mixed $class): bool => is_string($class) && is_subclass_of($class, ModuleServiceProvider::class),
        ));
    }

    /**
     * The provider of every module under app/Modules.
     *
     * @return list<class-string<ModuleServiceProvider>>
     */
    private function classesOnDisk(): array
    {
        $classes = [];

        foreach (glob(app_path('Modules/*/*ServiceProvider.php')) ?: [] as $file) {
            preg_match('/^namespace\s+([^;]+);/m', (string) file_get_contents($file), $namespace);
            $class = isset($namespace[1]) ? $namespace[1].'\\'.basename($file, '.php') : null;

            if ($class !== null && is_subclass_of($class, ModuleServiceProvider::class)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    /**
     * @return list<string>
     */
    private function migrationsOf(ModuleServiceProvider $module): array
    {
        return array_map(
            static fn (string $file): string => basename($file, '.php'),
            glob($module->path('Database/Migrations/*.php')) ?: [],
        );
    }

    /**
     * @return list<string>
     */
    private function ran(): array
    {
        return $this->migrator->repositoryExists() ? array_values($this->migrator->getRepository()->getRan()) : [];
    }

    /**
     * The tables those migrations created, with how many rows each holds —
     * read from the migrations themselves, for reporting only. What drops
     * them is the migrations' own `down()`.
     *
     * @param  list<string>  $migrations
     * @return array<string, int>
     */
    private function tablesCreatedBy(ModuleServiceProvider $module, array $migrations): array
    {
        $tables = [];

        foreach ($migrations as $migration) {
            $source = (string) file_get_contents($module->path("Database/Migrations/{$migration}.php"));

            preg_match_all("/Schema::create\\(\\s*'([^']+)'/", $source, $created);

            foreach ($created[1] as $table) {
                $tables[$table] = Schema::hasTable($table) ? DB::table($table)->count() : 0;
            }
        }

        return $tables;
    }
}
