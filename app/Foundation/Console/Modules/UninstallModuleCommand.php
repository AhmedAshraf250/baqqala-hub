<?php

namespace App\Foundation\Console\Modules;

use App\Foundation\Modules\ModuleInspector;
use App\Foundation\Modules\Uninstall\ModuleUninstaller;
use Illuminate\Console\Command;
use Illuminate\Console\Prohibitable;
use RuntimeException;

use function Laravel\Prompts\text;

/**
 * Removes a disabled module's data, after saying exactly what that is.
 *
 *     php artisan app:module:uninstall catalog --dry-run   see what it would remove
 *     php artisan app:module:uninstall catalog             remove it, after typing its key
 *
 * Refuses a module that is still enabled, and one another module depends on
 * while that module is not uninstalled itself. Prohibited in production with
 * the other destructive commands. Leaves the code alone: delete the module's
 * folder and its line with git, where that can be undone.
 */
final class UninstallModuleCommand extends Command
{
    use Prohibitable;
    use WritesFootprint;

    protected $signature = 'app:module:uninstall
                            {module : The module key, or its provider class}
                            {--dry-run : Show what would be removed, and remove nothing}
                            {--force : Do not ask to confirm}';

    protected $description = 'Remove a disabled module\'s data: its tables, its permissions, and whatever it says it left behind';

    public function handle(ModuleInspector $inspector, ModuleUninstaller $uninstaller): int
    {
        if ($this->isProhibited()) {
            return self::FAILURE;
        }

        $module = $inspector->find((string) $this->argument('module'));

        if ($module === null) {
            $this->components->error("There is no module [{$this->argument('module')}]. See app:module:list.");

            return self::FAILURE;
        }

        $key = $module->key();
        $refusal = $uninstaller->refusal($module);

        if ($refusal !== null) {
            $this->components->error($refusal);

            return self::FAILURE;
        }

        $footprint = $inspector->footprint($module);

        if ($footprint->isEmpty()) {
            $this->components->info("Nothing of [{$key}] is left in the database.");

            return self::SUCCESS;
        }

        $this->components->twoColumnDetail("<fg=yellow>Uninstalling [{$key}] removes</>");
        $this->writeFootprint($footprint);

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        if (! $this->option('force') && text("Type [{$key}] to remove all of this") !== $key) {
            $this->components->warn('Nothing was removed.');

            return self::FAILURE;
        }

        try {
            $uninstaller->uninstall($module);
        } catch (RuntimeException $stopped) {
            $this->components->error($stopped->getMessage());

            return self::FAILURE;
        }

        $remaining = $inspector->footprint($module);

        if (! $remaining->isEmpty()) {
            $this->components->error("Some of [{$key}] is still there:");
            $this->writeFootprint($remaining);

            return self::FAILURE;
        }

        $this->components->info("Nothing of [{$key}] is left in the database.");
        $this->components->bulletList([
            "Delete its code with git: git rm -r {$this->relative($module->path())}",
            "Remove any override of its settings: config/{$key}.php, if there is one",
        ]);

        return self::SUCCESS;
    }
}
