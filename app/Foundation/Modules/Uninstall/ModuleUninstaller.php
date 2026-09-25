<?php

namespace App\Foundation\Modules\Uninstall;

use App\Foundation\Contracts\Modules\UninstallableInterface;
use App\Foundation\Contracts\Modules\UninstallStepInterface;
use App\Foundation\Modules\ModuleInspector;
use App\Foundation\Modules\ModuleServiceProvider;
use App\Foundation\Modules\ModuleState;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Removes a disabled module's footprint: everything of it that is stored.
 *
 * Disabling a module — taking its line out of `config/modules.php` — stops its
 * code and leaves its data, deliberately. This is the other half: its own
 * leftovers (through {@see UninstallableInterface}), what the shells hold for
 * it (through {@see UninstallStepInterface}), and last its tables, by rolling
 * back its migrations. What there is to remove is the inspector's
 * {@see ModuleInspector::footprint()}, and it must be empty afterwards.
 *
 * It removes data, never code. The module's folder and its line are in git,
 * where deleting them can be undone; the data is not.
 */
final readonly class ModuleUninstaller
{
    public function __construct(
        private ModuleInspector $inspector,
        private Migrator $migrator,
    ) {}

    /**
     * Why this module may not be uninstalled now, or null if it may.
     */
    public function refusal(ModuleServiceProvider $module): ?string
    {
        $key = $module->key();

        if ($this->inspector->isEnabled($module)) {
            return "[{$key}] is still enabled. Take it out of config/modules.php first, so nothing is running on its data.";
        }

        foreach ($this->inspector->dependents($module) as $dependent) {
            $refusal = match ($this->inspector->state($dependent)) {
                ModuleState::Enabled => "[{$dependent->key()}] depends on [{$key}] and is enabled. Disable and uninstall [{$dependent->key()}] first.",
                ModuleState::Disabled => "[{$dependent->key()}] depends on [{$key}] and is not uninstalled yet. Uninstall [{$dependent->key()}] first.",
                ModuleState::NotInstalled => null,
            };

            if ($refusal !== null) {
                return $refusal;
            }
        }

        return null;
    }

    /**
     * Remove it all: the module's own leftovers first, while its tables can
     * still be read; then what the shells hold for it; then its tables.
     *
     * @throws RuntimeException When the module's own `uninstall()` left
     *                          something. Nothing else has been touched: its
     *                          tables are still there to fix it from.
     */
    public function uninstall(ModuleServiceProvider $module): void
    {
        if ($module instanceof UninstallableInterface) {
            $module->uninstall();

            $left = $this->inspector->leftovers($module);

            if ($left !== []) {
                throw new RuntimeException("[{$module->key()}]'s own uninstall() left: ".implode('; ', $left).'. Its tables are untouched.');
            }
        }

        foreach ($this->inspector->steps() as $step) {
            $step->run($module);
        }

        $this->migrator->usingConnection(
            DB::getDefaultConnection(),
            fn () => $this->migrator->reset([$module->path('Database/Migrations')]),
        );
    }
}
