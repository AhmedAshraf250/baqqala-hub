<?php

namespace App\Admin\Contracts\Authorization;

use BackedEnum;

/**
 * Something an administrator may be allowed to do.
 *
 * Extends `BackedEnum` on purpose: a permission must be an enum case, so a
 * typo in code cannot become a silently-false check, and `->value` is the
 * string the permission tables store.
 *
 * Each module declares its own enum implementing this, beside the code it
 * protects, and takes it along when it is removed. Customers have no
 * permissions at all — this is the admin area's vocabulary.
 *
 * @property-read string $value
 */
interface PermissionInterface extends BackedEnum
{
    /**
     * The translated label shown on a permissions screen.
     */
    public function label(): string;

    /**
     * The heading this permission is grouped under — the module key, or
     * `system` for the shell's own.
     */
    public function group(): string;
}
