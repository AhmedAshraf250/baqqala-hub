<?php

namespace App\Modules\Customers\Contracts\Enums;

use App\Admin\Contracts\Authorization\PermissionInterface;
use Illuminate\Support\Str;

/**
 * What may be done with the people the shop deals with.
 *
 * Published, unlike other modules' permissions: this module has no screens,
 * and the screens that add and change customers — Accounts' — are the ones
 * that must check these.
 */
enum CustomerPermission: string implements PermissionInterface
{
    case View = 'customers.view';

    /** Add a new customer to the books — at the counter, while they wait. */
    case Create = 'customers.create';

    /** Change what is known about a customer, and keep the groups. */
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
