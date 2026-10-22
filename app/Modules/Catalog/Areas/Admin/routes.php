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
| the sidebar item that links here names the same one. Changing anything on
| them takes `catalog.manage`, asked by each action.
|
*/

// Every product, its price changed where it stands, and the drawer for the rest.
Route::livewire('products', 'catalog::admin.page.products')
    ->middleware('can:'.CatalogPermission::View->value)
    ->name('products.index');

// The shelves, in the order the picker shows them.
Route::livewire('categories', 'catalog::admin.page.categories')
    ->middleware('can:'.CatalogPermission::View->value)
    ->name('categories.index');

Route::get('stock', PlannedSectionController::class)
    ->defaults('description', 'catalog::module.planned.stock')
    ->middleware('can:'.CatalogPermission::AdjustStock->value)
    ->name('stock.index');
