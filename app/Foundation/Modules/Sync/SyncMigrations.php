<?php

namespace App\Foundation\Modules\Sync;

use App\Foundation\Contracts\Modules\SyncStepInterface;
use App\Foundation\Modules\ModuleInspector;
use App\Foundation\Modules\ModuleServiceProvider;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;

/**
 * Migrations in the code that have not run: tables the code expects and the
 * database does not have yet.
 */
final readonly class SyncMigrations implements SyncStepInterface
{
    public function __construct(
        private ModuleInspector $modules,
        private Migrator $migrator,
    ) {}

    public function title(): string
    {
        return 'Migrations';
    }

    public function findings(?ModuleServiceProvider $module = null): array
    {
        $findings = [];

        foreach ($module === null ? $this->modules->enabled() : [$module] as $each) {
            foreach ($this->modules->pendingMigrations($each) as $migration) {
                $findings[] = ["{$each->key()} · {$migration}", 'not run yet'];
            }
        }

        // The application's own, when the whole installation is asked about.
        if ($module === null) {
            foreach ($this->pendingApplicationMigrations() as $migration) {
                $findings[] = ["application · {$migration}", 'not run yet'];
            }
        }

        return $findings;
    }

    /**
     * Run them, oldest first, the way `php artisan migrate` does.
     */
    public function fix(?ModuleServiceProvider $module = null): void
    {
        $paths = $module === null
            ? [database_path('migrations'), ...array_map(
                static fn (ModuleServiceProvider $each): string => $each->path('Database/Migrations'),
                $this->modules->enabled(),
            )]
            : [$module->path('Database/Migrations')];

        $this->migrator->usingConnection(DB::getDefaultConnection(), function () use ($paths): void {
            if (! $this->migrator->repositoryExists()) {
                $this->migrator->getRepository()->createRepository();
            }

            $this->migrator->run($paths);
        });
    }

    /**
     * @return list<string>
     */
    private function pendingApplicationMigrations(): array
    {
        $ran = $this->migrator->repositoryExists() ? $this->migrator->getRepository()->getRan() : [];

        return array_values(array_diff(
            array_keys($this->migrator->getMigrationFiles([database_path('migrations')])),
            $ran,
        ));
    }
}
