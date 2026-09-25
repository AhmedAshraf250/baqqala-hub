<?php

namespace App\Admin\Authorization;

use App\Admin\Contracts\Authorization\PermissionInterface;
use Illuminate\Support\Str;

/**
 * What the admin shell itself protects.
 *
 * Only the panel's own machinery — who may enter it, and who may configure it.
 * Everything about the business belongs to a module's own permission enum, so
 * a module takes its vocabulary with it when it leaves.
 */
enum AdminPermission: string implements PermissionInterface
{
    case ViewAccessControl = 'access.view';

    case ManageAdministrators = 'access.administrators.manage';

    case ManageRoles = 'access.roles.manage';

    case ManageSettings = 'settings.manage';

    public function label(): string
    {
        return __('shell.permissions.'.Str::snake($this->name));
    }

    public function group(): string
    {
        return 'system';
    }
}
