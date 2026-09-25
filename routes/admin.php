<?php

use App\Admin\Authorization\AdminPermission;
use App\Admin\Http\Controllers\DashboardController;
use App\Admin\Http\Controllers\PlannedSectionController;
use App\Admin\Http\Controllers\SettingsController;
use App\Foundation\Area\Area;
use App\Foundation\Modules\ModuleRegistry;
use App\Foundation\Modules\ModuleServiceProvider;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Area Routes
|--------------------------------------------------------------------------
|
| Everything here is prefixed /admin, named admin.*, and already behind the
| admin guard, the area check, and email verification.
|
| This file declares the *shell* — the parts of the panel that exist whatever
| the business is. The business itself comes from the modules below, so adding
| a module never means editing this file.
|
*/

Route::redirect('/', '/admin/dashboard')->name('home');

Route::get('dashboard', DashboardController::class)->name('dashboard');

/*
|--------------------------------------------------------------------------
| Modules
|--------------------------------------------------------------------------
|
| Each module's declared admin routes, under a prefix and a name of its own:
| Catalog's `products` route is `/admin/catalog/products`, named
| `admin.catalog.products.index`. A module cannot step outside its namespace
| or widen what guards it — both are set here, around it.
|
*/

app(ModuleRegistry::class)->all()->each(static function (ModuleServiceProvider $module): void {
    $routes = $module->routesFor(Area::Admin);

    if ($routes !== null) {
        Route::prefix($module->key())->name($module->key().'.')->group($routes);
    }
});

/*
|--------------------------------------------------------------------------
| The signed-in administrator
|--------------------------------------------------------------------------
|
| Two different things, as the theme has them: `profile` is the person's own
| page, `settings` is where the panel is configured. Settings is one screen
| with tabs, not a route per pane, so switching panes never reloads the shell.
|
*/

Route::get('settings', SettingsController::class)->name('settings');

Route::livewire('profile', 'admin::page.profile')->name('profile');

/*
|--------------------------------------------------------------------------
| Access control
|--------------------------------------------------------------------------
|
| Who may enter the admin area, and what each of them may do. The most
| sensitive corner of the panel, so it re-asks for the password — while
| Settings stays open, because making someone re-authenticate to change a
| colour theme only trains them to type their password without reading it.
|
| TODO(access): the administrator list, inviting one, and assigning roles
| TODO(access): a role editor over the permission registry
|
*/

Route::prefix('access')
    ->name('access.')
    ->middleware(['can:'.AdminPermission::ViewAccessControl->value, 'admin.password.confirm'])
    ->group(function (): void {
        Route::get('administrators', PlannedSectionController::class)
            ->defaults('description', 'shell.planned.administrators')
            ->middleware('can:'.AdminPermission::ManageAdministrators->value)
            ->name('administrators.index');

        Route::get('roles', PlannedSectionController::class)
            ->defaults('description', 'shell.planned.roles')
            ->middleware('can:'.AdminPermission::ManageRoles->value)
            ->name('roles.index');
    });
