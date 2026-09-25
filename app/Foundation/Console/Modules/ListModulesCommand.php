<?php

namespace App\Foundation\Console\Modules;

use App\Foundation\Contracts\Modules\DependsOnModulesInterface;
use App\Foundation\Modules\ModuleInspector;
use App\Foundation\Modules\ModuleServiceProvider;
use Illuminate\Console\Command;

/**
 * Every module in the codebase, and where each stands in this installation.
 *
 *     php artisan app:module:list
 *
 * Disabled modules are listed too — found on disk, since they are not
 * registered — because a disabled module with data left is exactly what
 * somebody needs to be told about.
 */
final class ListModulesCommand extends Command
{
    protected $signature = 'app:module:list';

    protected $description = 'List every module: whether it is enabled, what it needs, and whether its migrations have run';

    public function handle(ModuleInspector $inspector): int
    {
        $this->table(
            ['Module', 'Name', 'State', 'Depends on', 'Migrations'],
            array_map(static fn (ModuleServiceProvider $module): array => [
                $module->key(),
                trans($module->name(), locale: 'en'),
                $inspector->state($module)->label(),
                $module instanceof DependsOnModulesInterface ? implode(', ', $module->dependsOn()) : '',
                self::migrations($inspector, $module),
            ], $inspector->all()),
        );

        $this->line('  <fg=gray>php artisan app:module:show {module} shows everything about one of them.</>');

        return self::SUCCESS;
    }

    private static function migrations(ModuleInspector $inspector, ModuleServiceProvider $module): string
    {
        $ran = count($inspector->ranMigrations($module));

        // Only an enabled module's migrations are waiting: `migrate` never
        // runs a disabled one's.
        $pending = $inspector->isEnabled($module) ? count($inspector->pendingMigrations($module)) : 0;

        return match (true) {
            $ran + $pending === 0 => '—',
            $pending === 0 => "{$ran} ran",
            default => "{$ran} ran, <fg=yellow>{$pending} pending</>",
        };
    }
}
