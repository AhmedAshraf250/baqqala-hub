<?php

use App\Admin\Http\Controllers\PlannedSectionController;
use App\Modules\Sales\Authorization\SalePermission;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Sales — admin screens
|--------------------------------------------------------------------------
|
| Loaded by the admin shell under `/admin/sales`, named `admin.sales.…`, and
| already behind the admin guard. Every screen names the permission it needs;
| the sidebar item that links here names the same one.
|
*/

Route::get('/', PlannedSectionController::class)
    ->defaults('description', 'sales::module.planned.sales')
    ->middleware('can:'.SalePermission::View->value)
    ->name('index');
