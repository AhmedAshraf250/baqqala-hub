<?php

namespace App\Foundation\Contracts\Modules;

use App\Foundation\Modules\ModuleServiceProvider;

/**
 * Something a layer holds for every module, removed when a module is
 * uninstalled.
 *
 * How the foundation clears what it does not know about. The admin shell
 * keeps each module's permissions, so it contributes the step that removes
 * them, tagged with this interface's name in its provider; the foundation runs
 * whatever is tagged and never names the shell.
 *
 * Every step runs on every uninstall, handed the one module being removed. It
 * removes what it holds for that module and nothing else — a step that ignored
 * its argument would take another module's data with it. A module's own
 * cleanup is not a step: it is {@see UninstallableInterface}.
 */
interface UninstallStepInterface
{
    /**
     * What this step would remove for the given module, one line each. Empty
     * when it holds nothing for it.
     *
     * @return list<string>
     */
    public function describe(ModuleServiceProvider $module): array;

    /**
     * Remove what this step holds for the given module, and only that.
     */
    public function run(ModuleServiceProvider $module): void;
}
