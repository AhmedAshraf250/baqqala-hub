<?php

namespace App\Modules\Suppliers\Authorization;

use App\Admin\Contracts\Authorization\PermissionInterface;
use Illuminate\Support\Str;

/**
 * What may be done with the businesses the shop buys from.
 */
enum SupplierPermission: string implements PermissionInterface
{
    case View = 'suppliers.view';

    case Manage = 'suppliers.manage';

    public function label(): string
    {
        return __('suppliers::module.permissions.'.Str::snake($this->name));
    }

    public function group(): string
    {
        return 'suppliers';
    }
}
