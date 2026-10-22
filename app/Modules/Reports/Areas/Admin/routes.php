<?php

use App\Modules\Reports\Authorization\ReportPermission;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Reports — admin screens
|--------------------------------------------------------------------------
|
| Loaded by the admin shell under `/admin/reports`, named `admin.reports.…`, and
| already behind the admin guard. Every screen names the permission it needs;
| the sidebar item that links here names the same one.
|
*/

Route::middleware('can:'.ReportPermission::View->value)->group(function (): void {
    // The reports, each with its headline figure.
    Route::livewire('/', 'reports::admin.page.index')->name('index');

    // What is owed now: in all, by group, and who owes most.
    Route::livewire('debts', 'reports::admin.page.debts')->name('debts');

    // Who has gone longest without paying, and the list to ring.
    Route::livewire('aging', 'reports::admin.page.aging')->name('aging');

    // What was taken and paid, day by day or month by month.
    Route::livewire('activity', 'reports::admin.page.activity')->name('activity');

    // Each person's share, and the mistakes they put right.
    Route::livewire('staff', 'reports::admin.page.staff')->name('staff');

    // Every void, undo, and correction, and why.
    Route::livewire('corrections', 'reports::admin.page.corrections')->name('corrections');

    // What was taken on account most, by product and by shelf.
    Route::livewire('products', 'reports::admin.page.products')->name('products');
});
