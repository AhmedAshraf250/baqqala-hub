<?php

namespace App\Modules\Customers\Authorization;

use App\Admin\Contracts\Authorization\PermissionInterface;
use Illuminate\Support\Str;

/**
 * What may be done with the people the shop deals with.
 */
enum CustomerPermission: string implements PermissionInterface
{
    case View = 'customers.view';

    case Manage = 'customers.manage';

    case GrantPortalAccess = 'customers.portal.grant';

    public function label(): string
    {
        return __('customers::module.permissions.'.Str::snake($this->name));
    }

    public function group(): string
    {
        return 'customers';
    }
}
