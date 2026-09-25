<?php

use App\Admin\Http\Controllers\PlannedSectionController;
use App\Modules\Customers\Authorization\CustomerPermission;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Customers — admin screens
|--------------------------------------------------------------------------
|
| Loaded by the admin shell under `/admin/customers`, named `admin.customers.…`, and
| already behind the admin guard. Every screen names the permission it needs;
| the sidebar item that links here names the same one.
|
*/

Route::get('/', PlannedSectionController::class)
    ->defaults('description', 'customers::module.planned.customers')
    ->middleware('can:'.CustomerPermission::View->value)
    ->name('index');
