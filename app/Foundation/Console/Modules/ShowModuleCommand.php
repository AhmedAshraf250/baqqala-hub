<?php

namespace App\Foundation\Console\Modules;

use App\Foundation\Area\Area;
use App\Foundation\Contracts\Modules\DependsOnModulesInterface;
use App\Foundation\Modules\ModuleInspector;
use App\Foundation\Modules\ModuleServiceProvider;
use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;

/**
 * Everything about one module: what it is, what it declares, and what of it
 * is stored.
 *
 *     php artisan app:module:show customers
 *
 * What it declares is read from the provider, so it is shown for a disabled
 * module too; what it has registered — routes, commands — only exists while
 * it is enabled.
 */
final class ShowModuleCommand extends Command
{
    use WritesFootprint;

    protected $signature = 'app:module:show {module : The module key, or its provider class}';

    protected $description = 'Show one module: what it declares, what it depends on, and what of it is stored';

    public function handle(ModuleInspector $inspector, Router $router): int
    {
        $module = $inspector->find((string) $this->argument('module'));

        if ($module === null) {
            $this->components->error("There is no module [{$this->argument('module')}]. See app:module:list.");

            return self::FAILURE;
        }

        $enabled = $inspector->isEnabled($module);

        $this->newLine();
        $this->components->twoColumnDetail('<fg=green;options=bold>'.trans($module->name(), locale: 'en').'</>', $inspector->state($module)->label());
        $this->components->twoColumnDetail('Key', $module->key());
        $this->components->twoColumnDetail('Provider', $module::class);
        $this->components->twoColumnDetail('Folder', $this->relative($module->path()));
        $this->components->twoColumnDetail('Depends on', $this->listed($module instanceof DependsOnModulesInterface ? $module->dependsOn() : []));
        $this->components->twoColumnDetail('Needed by', $this->listed(array_map(
            static fn (ModuleServiceProvider $dependent): string => $dependent->key(),
            $inspector->dependents($module),
        )));
        $this->components->twoColumnDetail('Implements', $this->listed($this->capabilities($module)));

        $this->newLine();
        $this->components->twoColumnDetail('<fg=green;options=bold>Declares</>');
        $this->components->twoColumnDetail('Config', $this->config($module));

        foreach (Area::cases() as $area) {
            $this->components->twoColumnDetail("Routes · {$area->value}", $this->routes($module, $area, $enabled, $router));
        }

        $this->components->twoColumnDetail('Publishes', $this->listed($this->contracts($module)));
        $this->components->twoColumnDetail('Commands', $enabled ? $this->listed($this->commandsOf($module)) : '<fg=gray>not registered while disabled</>');

        $this->newLine();
        $this->components->twoColumnDetail('<fg=green;options=bold>Stored</>');
        $this->components->twoColumnDetail('Migrations', sprintf(
            $enabled ? '%d ran, %d pending' : '%d ran, %d not run while it is disabled',
            count($inspector->ranMigrations($module)),
            count($inspector->pendingMigrations($module)),
        ));

        $footprint = $inspector->footprint($module);

        $footprint->isEmpty()
            ? $this->components->twoColumnDetail('<fg=gray>Nothing of it is stored.</>')
            : $this->writeFootprint($footprint);

        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * The capability interfaces the provider implements — what it adds to the
     * areas, and whether it cleans up after itself.
     *
     * @return list<string>
     */
    private function capabilities(ModuleServiceProvider $module): array
    {
        $interfaces = array_map(
            static fn (string $interface): string => class_basename($interface),
            array_filter(class_implements($module), static fn (string $interface): bool => str_starts_with($interface, 'App\\')),
        );

        sort($interfaces);

        return $interfaces;
    }

    /**
     * The contracts it binds for other modules to resolve.
     *
     * @return list<string>
     */
    private function contracts(ModuleServiceProvider $module): array
    {
        $contracts = array_filter(
            array_keys(property_exists($module, 'bindings') ? $module->bindings : []),
            is_string(...),
        );

        return array_values(array_map(class_basename(...), $contracts));
    }

    private function config(ModuleServiceProvider $module): string
    {
        $file = $module->configFile();

        return $file === null
            ? '—'
            : $this->inModule($module, $file)." → config('{$module->key()}')";
    }

    private function routes(ModuleServiceProvider $module, Area $area, bool $enabled, Router $router): string
    {
        $file = $module->routesFor($area);

        if ($file === null) {
            return '—';
        }

        if (! $enabled) {
            return $this->inModule($module, $file);
        }

        $prefix = "{$area->value}.{$module->key()}.";
        $count = count(array_filter(
            $router->getRoutes()->getRoutes(),
            static fn (Route $route): bool => str_starts_with((string) $route->getName(), $prefix),
        ));

        return sprintf('%s · %d %s · route:list --name=%s', $this->inModule($module, $file), $count, Str::plural('route', $count), $prefix);
    }

    /**
     * A path inside the module, as it appears under the module's folder.
     */
    private function inModule(ModuleServiceProvider $module, string $path): string
    {
        return Str::after($path, $module->path().'/');
    }

    /**
     * The commands it registered: a module's are named `app:{key}:…`.
     *
     * @return list<string>
     */
    private function commandsOf(ModuleServiceProvider $module): array
    {
        $prefix = "app:{$module->key()}:";

        return array_values(array_filter(
            array_keys($this->getApplication()?->all() ?? []),
            static fn (string $name): bool => str_starts_with($name, $prefix),
        ));
    }

    /**
     * @param  list<string>  $items
     */
    private function listed(array $items): string
    {
        return $items === [] ? '—' : implode(', ', $items);
    }
}
