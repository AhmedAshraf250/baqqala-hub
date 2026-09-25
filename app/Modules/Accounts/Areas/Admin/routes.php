<?php

use App\Admin\Http\Controllers\PlannedSectionController;
use App\Modules\Accounts\Authorization\AccountPermission;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Accounts — admin screens
|--------------------------------------------------------------------------
|
| Loaded by the admin shell under `/admin/accounts`, named `admin.accounts.…`, and
| already behind the admin guard. Every screen names the permission it needs;
| the sidebar item that links here names the same one.
|
*/

Route::get('/', PlannedSectionController::class)
    ->defaults('description', 'accounts::module.planned.accounts')
    ->middleware('can:'.AccountPermission::View->value)
    ->name('index');
