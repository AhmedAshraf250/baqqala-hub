<?php

use App\Admin\Http\Controllers\PlannedSectionController;
use App\Modules\Catalog\Authorization\CatalogPermission;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Catalog — admin screens
|--------------------------------------------------------------------------
|
| Loaded by the admin shell under `/admin/catalog`, named `admin.catalog.…`, and
| already behind the admin guard. Every screen names the permission it needs;
| the sidebar item that links here names the same one.
|
*/

Route::get('products', PlannedSectionController::class)
    ->defaults('description', 'catalog::module.planned.products')
    ->middleware('can:'.CatalogPermission::View->value)
    ->name('products.index');

Route::get('categories', PlannedSectionController::class)
    ->defaults('description', 'catalog::module.planned.categories')
    ->middleware('can:'.CatalogPermission::View->value)
    ->name('categories.index');

Route::get('stock', PlannedSectionController::class)
    ->defaults('description', 'catalog::module.planned.stock')
    ->middleware('can:'.CatalogPermission::AdjustStock->value)
    ->name('stock.index');
