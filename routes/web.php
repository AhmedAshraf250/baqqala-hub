<?php

use App\Admin\Http\Controllers\UpdateLocaleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| This file only wires the areas together. Each area owns its own route file,
| and each module its own, so adding a module never means editing shared
| routing.
|
*/

// Every authentication endpoint in the application, per area.
require __DIR__.'/auth.php';

Route::middleware(['auth:admin', 'verified', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(base_path('routes/admin.php'));

// No prefix: the frontend is the site itself. Its account pages are guarded
// inside the file, because the rest of it is public.
Route::name('frontend.')->group(base_path('routes/frontend.php'));

// Outside the authenticated group because the sign-in screen has a switcher
// too, and inside `/admin` so the request opens the admin session.
Route::post('admin/locale', UpdateLocaleController::class)->name('admin.locale.update');

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('frontend.account.settings.security'),
        'manage' => route('frontend.account.settings.security'),
    ]);
})->name('well-known.passkeys');
