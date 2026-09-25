<?php

namespace App\Modules\Catalog\Authorization;

use App\Admin\Contracts\Authorization\PermissionInterface;
use Illuminate\Support\Str;

/**
 * What may be done with the catalog.
 */
enum CatalogPermission: string implements PermissionInterface
{
    case View = 'catalog.view';

    case Manage = 'catalog.manage';

    case AdjustStock = 'catalog.stock.adjust';

    public function label(): string
    {
        return __('catalog::module.permissions.'.Str::snake($this->name));
    }

    public function group(): string
    {
        return 'catalog';
    }
}
