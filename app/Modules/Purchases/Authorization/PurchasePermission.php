<?php

namespace App\Modules\Purchases\Authorization;

use App\Admin\Contracts\Authorization\PermissionInterface;
use Illuminate\Support\Str;

/**
 * What may be done with goods coming into the shop.
 */
enum PurchasePermission: string implements PermissionInterface
{
    case View = 'purchases.view';

    case Manage = 'purchases.manage';

    public function label(): string
    {
        return __('purchases::module.permissions.'.Str::snake($this->name));
    }

    public function group(): string
    {
        return 'purchases';
    }
}
