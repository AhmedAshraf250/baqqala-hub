<?php

use App\Foundation\Area\Area;
use App\Foundation\Modules\ModuleRegistry;
use App\Foundation\Modules\ModuleServiceProvider;
use App\Frontend\Http\Controllers\AccountController;
use App\Frontend\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Frontend Routes
|--------------------------------------------------------------------------
|
| Everything the public faces, named frontend.* and served from the root of
| the site. Two parts: the site itself, open to anyone, and /account, the
| signed-in visitor's own pages behind the frontend guard and the area check.
| Signing in and its flows are in routes/auth.php, because Fortify needs its
| own route names there.
|
*/

Route::get('/', HomeController::class)->name('home');

/*
|--------------------------------------------------------------------------
| Account
|--------------------------------------------------------------------------
|
| For this product the visitor is a customer following their tab; for a
| school, a parent. The pages are the same kind of thing either way: the
| person's own.
|
*/

Route::middleware(['auth:web', 'verified', 'frontend'])
    ->prefix('account')
    ->name('account.')
    ->group(function (): void {
        Route::get('/', AccountController::class)->name('dashboard');

        Route::prefix('settings')->name('settings.')->group(function (): void {
            // The landing page *is* the profile pane, not a redirect to it:
            // bouncing a visitor one hop to reach the page they asked for is a
            // URL apologising for itself.
            Route::livewire('/', 'frontend::page.account.settings.profile')->name('home');

            Route::livewire('profile', 'frontend::page.account.settings.profile')->name('profile');
            Route::livewire('appearance', 'frontend::page.account.settings.appearance')->name('appearance');

            Route::livewire('security', 'frontend::page.account.settings.security')
                ->middleware('password.confirm')
                ->name('security');
        });
    });

/*
|--------------------------------------------------------------------------
| Modules
|--------------------------------------------------------------------------
|
| Each module's declared frontend routes, under `/{key}` and
| `frontend.{key}.…`. Public by default — a product page anyone can read. A
| screen that is the visitor's own adds the account's guard itself:
| `->middleware(['auth:web', 'verified', 'frontend'])`.
|
*/

app(ModuleRegistry::class)->all()->each(static function (ModuleServiceProvider $module): void {
    $routes = $module->routesFor(Area::Frontend);

    if ($routes !== null) {
        Route::prefix($module->key())->name($module->key().'.')->group($routes);
    }
});
