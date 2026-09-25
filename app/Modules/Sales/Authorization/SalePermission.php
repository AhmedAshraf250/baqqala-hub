<?php

namespace App\Modules\Sales\Authorization;

use App\Admin\Contracts\Authorization\PermissionInterface;
use Illuminate\Support\Str;

/**
 * What may be done with goods leaving the shop.
 */
enum SalePermission: string implements PermissionInterface
{
    case View = 'sales.view';

    case Manage = 'sales.manage';

    public function label(): string
    {
        return __('sales::module.permissions.'.Str::snake($this->name));
    }

    public function group(): string
    {
        return 'sales';
    }
}
