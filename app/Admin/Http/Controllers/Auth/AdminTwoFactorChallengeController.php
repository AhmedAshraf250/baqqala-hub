<?php

namespace App\Admin\Http\Controllers\Auth;

use App\Admin\Http\Requests\Auth\AdminTwoFactorLoginRequest;
use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\AdminUser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

/**
 * The second factor for an administrator's sign-in.
 *
 * The user is not signed in while this runs: their id sits in the session as a
 * pending login, exactly as Fortify does it, and the guard only receives them
 * once a code or a recovery code verifies.
 */
class AdminTwoFactorChallengeController
{
    public function create(Request $request): View|RedirectResponse
    {
        if ($this->pendingUser($request) === null) {
            return redirect()->route(Area::Admin->loginRoute());
        }

        return view('admin::auth.two-factor-challenge');
    }

    public function store(
        AdminTwoFactorLoginRequest $request,
        TwoFactorAuthenticationProvider $provider,
    ): RedirectResponse {
        $user = $this->pendingUser($request);

        if ($user === null) {
            return redirect()->route(Area::Admin->loginRoute());
        }

        $verified = $request->filled('recovery_code')
            ? $this->consumeRecoveryCode($user, $request->string('recovery_code')->toString())
            : $provider->verify(
                decrypt($user->two_factor_secret),
                $request->string('code')->toString(),
            );

        if (! $verified) {
            throw ValidationException::withMessages([
                $request->filled('recovery_code') ? 'recovery_code' : 'code' => [
                    trans('shell.auth.two_factor_failed'),
                ],
            ]);
        }

        $remember = (bool) $request->session()->pull('login.remember', false);
        $request->session()->forget('login.id');

        Auth::guard(Area::Admin->guard())->login($user, $remember);

        $request->session()->regenerate();

        return redirect()->intended(route(Area::Admin->homeRoute()));
    }

    /**
     * The administrator waiting on a second factor, if the session holds one.
     */
    private function pendingUser(Request $request): ?AdminUser
    {
        $id = $request->session()->get('login.id');

        if ($id === null) {
            return null;
        }

        $user = AdminUser::query()->find($id);

        return $user instanceof AdminUser ? $user : null;
    }

    /**
     * Verify a recovery code and burn it.
     *
     * A recovery code is single use; leaving a spent one in place would turn a
     * one-time escape hatch into a permanent second password.
     */
    private function consumeRecoveryCode(AdminUser $user, string $submitted): bool
    {
        $codes = json_decode(decrypt($user->two_factor_recovery_codes), true) ?: [];

        $index = array_search($submitted, $codes, strict: true);

        if ($index === false) {
            return false;
        }

        unset($codes[$index]);

        $user->forceFill([
            'two_factor_recovery_codes' => encrypt(json_encode(array_values($codes))),
        ])->save();

        return true;
    }
}
