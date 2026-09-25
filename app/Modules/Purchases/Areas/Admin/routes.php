<?php

use App\Admin\Http\Controllers\PlannedSectionController;
use App\Modules\Purchases\Authorization\PurchasePermission;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Purchases — admin screens
|--------------------------------------------------------------------------
|
| Loaded by the admin shell under `/admin/purchases`, named `admin.purchases.…`, and
| already behind the admin guard. Every screen names the permission it needs;
| the sidebar item that links here names the same one.
|
*/

Route::get('/', PlannedSectionController::class)
    ->defaults('description', 'purchases::module.planned.purchases')
    ->middleware('can:'.PurchasePermission::View->value)
    ->name('index');
