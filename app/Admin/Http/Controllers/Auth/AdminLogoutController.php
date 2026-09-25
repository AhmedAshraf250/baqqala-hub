<?php

namespace App\Admin\Http\Controllers\Auth;

use App\Admin\Http\Middleware\RequireAdminPasswordConfirmation;
use App\Foundation\Area\Area;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Ends an administrator's session.
 *
 * Only the admin guard is logged out: a customer signed in on the same browser
 * keeps their own session, because the two guards do not share one.
 */
class AdminLogoutController
{
    public function __invoke(Request $request): RedirectResponse
    {
        Auth::guard(Area::Admin->guard())->logout();

        RequireAdminPasswordConfirmation::revoke($request);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route(Area::Admin->loginRoute());
    }
}
