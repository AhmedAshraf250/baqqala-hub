<?php

namespace App\Modules\Reports\Authorization;

use App\Admin\Contracts\Authorization\PermissionInterface;
use Illuminate\Support\Str;

/**
 * Who may read the shop's numbers.
 */
enum ReportPermission: string implements PermissionInterface
{
    case View = 'reports.view';

    public function label(): string
    {
        return __('reports::module.permissions.'.Str::snake($this->name));
    }

    public function group(): string
    {
        return 'reports';
    }
}
