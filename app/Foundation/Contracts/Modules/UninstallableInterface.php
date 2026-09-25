<?php

namespace App\Foundation\Contracts\Modules;

/**
 * A module that leaves something behind outside its own tables.
 *
 * Its tables, and what the shells hold for it — its permissions — are removed
 * for every module by `app:module:uninstall` without the module writing a
 * line. This is for the rest: files it stored, a remote account it opened, a
 * key it wrote somewhere shared. The developer who wrote those writes this.
 *
 * The tool checks the module's state before calling it — disabled, and no
 * module that depends on it still installed — and calls it before the
 * module's tables are dropped, so it can still read them to find what to
 * delete. Both methods run only while those tables exist.
 */
interface UninstallableInterface
{
    /**
     * What `uninstall()` would remove right now, one line each — shown before
     * anything is deleted, so whoever runs it knows what they are agreeing to,
     * and asked again right after `uninstall()`, before the tables go, to
     * prove it is gone. Empty when nothing is left.
     *
     * @return list<string>
     */
    public function leftovers(): array;

    /**
     * Remove it.
     */
    public function uninstall(): void;
}
