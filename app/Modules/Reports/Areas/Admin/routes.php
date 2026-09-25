<?php

use App\Admin\Http\Controllers\PlannedSectionController;
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

Route::get('/', PlannedSectionController::class)
    ->defaults('description', 'reports::module.planned.reports')
    ->middleware('can:'.ReportPermission::View->value)
    ->name('index');
