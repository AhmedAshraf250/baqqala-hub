<?php

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

// The counter's workspace: every customer, where each account stands, and
// the drawer that records what was taken and what was paid.
Route::livewire('/', 'accounts::admin.page.customers')
    ->middleware('can:'.AccountPermission::View->value)
    ->name('index');

// The day's book: every entry of a business day, and the cash each person took.
Route::livewire('journal', 'accounts::admin.page.journal')
    ->middleware('can:'.AccountPermission::View->value)
    ->name('journal');

// One customer's account: where it stands, its statement, and the
// corrections made from it.
Route::livewire('{customer}', 'accounts::admin.page.account')
    ->whereNumber('customer')
    ->middleware('can:'.AccountPermission::View->value)
    ->name('show');
