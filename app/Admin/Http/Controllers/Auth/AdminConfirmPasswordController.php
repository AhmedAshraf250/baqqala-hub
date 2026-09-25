<?php

namespace App\Admin\Http\Controllers\Auth;

use App\Admin\Http\Middleware\RequireAdminPasswordConfirmation;
use App\Foundation\Area\Area;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Re-proves an administrator's password before a sensitive admin screen.
 *
 * The password is checked against the user on the *admin* guard, and the grant
 * is recorded under an admin-only session key, so it can never be satisfied by
 * a customer signed in on the same browser.
 */
class AdminConfirmPasswordController
{
    public function show(): View
    {
        return view('admin::auth.confirm-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ], [], ['password' => __('shell.auth.password')]);

        $user = Area::Admin->user();

        if ($user === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'password' => [__('shell.auth.password_incorrect')],
            ]);
        }

        RequireAdminPasswordConfirmation::grant($request);

        return redirect()->intended(route(Area::Admin->homeRoute()));
    }
}
