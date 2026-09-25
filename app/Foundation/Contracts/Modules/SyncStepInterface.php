<?php

namespace App\Foundation\Contracts\Modules;

use App\Foundation\Modules\ModuleServiceProvider;

/**
 * One kind of thing that is stored, and so can fall behind the code.
 *
 * After a module changes, most of the change needs nothing: routes, the
 * sidebar, strings, and settings are read from its code. What is stored can
 * lag — a migration not run, a permission not in the database, a cache built
 * from older code. Each layer that stores something contributes a step,
 * tagged with this interface's name in its provider. `app:sync`
 * shows what every step finds, and fixes it only once it is told to.
 *
 * A step brings its own kind of data in line with the code. Removing a
 * module's data when it is uninstalled is a different job, with different
 * classes: {@see UninstallStepInterface}.
 */
interface SyncStepInterface
{
    /**
     * The heading its findings are listed under.
     */
    public function title(): string;

    /**
     * What is out of sync, one finding each: what, and what is wrong with
     * it. Empty when in sync. Given a module, only what concerns that module.
     * Looks only.
     *
     * @return list<array{0: string, 1: string}>
     */
    public function findings(?ModuleServiceProvider $module = null): array;

    /**
     * Bring what it stores in line with the code — for the given module
     * only, when one is given.
     */
    public function fix(?ModuleServiceProvider $module = null): void;
}
