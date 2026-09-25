<?php

namespace App\Frontend\Http\Controllers\Auth;

use Illuminate\Contracts\View\View;
use Laravel\Fortify\Features;

/**
 * The frontend's front door, at /login.
 *
 * Only the screen: the POST goes to Fortify's own controller, so rate
 * limiting, two-factor, and session regeneration stay in one audited place.
 */
class LoginController
{
    public function create(): View
    {
        return view('frontend::auth.login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
        ]);
    }
}
