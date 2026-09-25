<?php

namespace App\Admin\Contracts\Authorization;

/**
 * A module that defines what may be done with it.
 *
 * Each module owns its own permission enum, so the vocabulary lives beside the
 * code it protects and leaves with it.
 */
interface ProvidesPermissionsInterface
{
    /**
     * Every permission this module defines.
     *
     * @return list<PermissionInterface>
     */
    public function permissions(): array;
}
