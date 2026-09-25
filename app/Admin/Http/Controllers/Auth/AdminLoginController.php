<?php

namespace App\Admin\Http\Controllers\Auth;

use App\Admin\Http\Requests\Auth\AdminLoginRequest;
use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\AdminUser;
use Illuminate\Auth\Events\Failed;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

/**
 * The admin area's front door, at /admin/login.
 *
 * This area authenticates on its own `admin` guard rather than through
 * Fortify, which supports a single guard. The two guards keep separate session
 * keys, so a customer session cannot be resolved here even if a route were to
 * lose its middleware.
 *
 * The lookup goes through {@see AdminUser}, whose global scope hides customer
 * rows entirely — a customer's credentials do not fail a role check here, they
 * find no record at all.
 */
class AdminLoginController
{
    /**
     * A fixed bcrypt hash compared against when no account matched, so an
     * unknown address costs the same time as a known one.
     */
    private const PlaceholderHash = '$2y$12$a9Vv1nXbfXO0gOoZ2Xh0Ye0sJ2R6bqQ1s0k0j2P0p8kZgYq1t6G3G';

    public function create(): View
    {
        return view('admin::auth.login');
    }

    public function store(AdminLoginRequest $request): RedirectResponse
    {
        $user = $this->findAdministrator($request);

        if ($user === null) {
            event(new Failed(Area::Admin->guard(), null, $request->credentials()));

            throw ValidationException::withMessages([
                Fortify::username() => [trans('auth.failed')],
            ]);
        }

        if ($this->requiresTwoFactor($user)) {
            return $this->challengeTwoFactor($request, $user);
        }

        Auth::guard(Area::Admin->guard())->login($user, $request->boolean('remember'));

        $request->session()->regenerate();

        return redirect()->intended(route(Area::Admin->homeRoute()));
    }

    /**
     * Find the administrator whose credentials these are, if any.
     *
     * Password verification always runs, even when no record matched, so the
     * response time does not reveal whether the address exists.
     */
    private function findAdministrator(AdminLoginRequest $request): ?AdminUser
    {
        $user = AdminUser::query()
            ->where(Fortify::username(), $request->username())
            ->first();

        // Always verify a hash, even with no match, so the response time does
        // not reveal whether the address exists.
        $matches = Hash::check(
            $request->string('password')->toString(),
            $user === null ? self::PlaceholderHash : $user->password,
        );

        return $user !== null && $matches ? $user : null;
    }

    /**
     * Whether this account has a confirmed second factor.
     */
    private function requiresTwoFactor(AdminUser $user): bool
    {
        return $user->two_factor_secret !== null
            && $user->two_factor_confirmed_at !== null;
    }

    /**
     * Park the login and send the user to the challenge screen.
     *
     * Nothing is signed in until the second factor is verified; the pending
     * login lives in the session exactly as Fortify does it.
     */
    private function challengeTwoFactor(AdminLoginRequest $request, AdminUser $user): RedirectResponse
    {
        $request->session()->put([
            'login.id' => $user->getKey(),
            'login.remember' => $request->boolean('remember'),
        ]);

        return redirect()->route('admin.two-factor.login');
    }
}
