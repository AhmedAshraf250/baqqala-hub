<?php

use App\Admin\Http\Controllers\PlannedSectionController;
use App\Modules\Suppliers\Authorization\SupplierPermission;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Suppliers — admin screens
|--------------------------------------------------------------------------
|
| Loaded by the admin shell under `/admin/suppliers`, named `admin.suppliers.…`, and
| already behind the admin guard. Every screen names the permission it needs;
| the sidebar item that links here names the same one.
|
*/

Route::get('/', PlannedSectionController::class)
    ->defaults('description', 'suppliers::module.planned.suppliers')
    ->middleware('can:'.SupplierPermission::View->value)
    ->name('index');
