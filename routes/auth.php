<?php

use App\Admin\Http\Controllers\Auth\AdminConfirmPasswordController;
use App\Admin\Http\Controllers\Auth\AdminLoginController;
use App\Admin\Http\Controllers\Auth\AdminLogoutController;
use App\Admin\Http\Controllers\Auth\AdminTwoFactorChallengeController;
use App\Frontend\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\ConfirmablePasswordController;
use Laravel\Fortify\Http\Controllers\ConfirmedPasswordStatusController;
use Laravel\Fortify\Http\Controllers\ConfirmedTwoFactorAuthenticationController;
use Laravel\Fortify\Http\Controllers\EmailVerificationNotificationController;
use Laravel\Fortify\Http\Controllers\EmailVerificationPromptController;
use Laravel\Fortify\Http\Controllers\NewPasswordController;
use Laravel\Fortify\Http\Controllers\PasswordController;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController;
use Laravel\Fortify\Http\Controllers\ProfileInformationController;
use Laravel\Fortify\Http\Controllers\RecoveryCodeController;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticationController;
use Laravel\Fortify\Http\Controllers\TwoFactorQrCodeController;
use Laravel\Fortify\Http\Controllers\TwoFactorSecretKeyController;
use Laravel\Fortify\Http\Controllers\VerifyEmailController;
use Laravel\Passkeys\Http\Controllers\PasskeyConfirmationController;
use Laravel\Passkeys\Http\Controllers\PasskeyLoginController;
use Laravel\Passkeys\Http\Controllers\PasskeyRegistrationController;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
|
| Fortify's own route registration is switched off in FrontendServiceProvider
| (`Fortify::ignoreRoutes()`), so every auth endpoint in the application is
| declared here where it can be read, reordered, renamed, and guarded.
|
| The two areas have separate front doors, and neither offers registration:
|
|   /admin/login   administrators, on the admin guard, AdminLTE screens
|   /login         the frontend, on the web guard, through Fortify, Flux screens
|
| Each front door looks logins up through its own area's model, so the other
| area's credentials find no record at all. The admin flow is ours, in
| `App\Admin\Http\Controllers\Auth`; the frontend's is Fortify's own
| controllers, so rate limiting, session fixation protection, and recovery
| codes stay in one audited implementation.
|
*/

$loginLimiter = config('fortify.limiters.login');
$twoFactorLimiter = config('fortify.limiters.two-factor');
$passkeyLimiter = config('fortify.limiters.passkeys');
$verificationLimiter = config('fortify.limiters.verification', '6,1');

Route::middleware(config('fortify.middleware', ['web']))->group(function () use (
    $loginLimiter,
    $twoFactorLimiter,
    $passkeyLimiter,
    $verificationLimiter,
) {

    /*
    |----------------------------------------------------------------------
    | Admin front door — /admin/login
    |----------------------------------------------------------------------
    |
    | Administrators are created from the console (`app:admin:grant`) or
    | from inside the admin area; there is deliberately no registration and no
    | password-reset request form here.
    |
    */

    Route::prefix('admin')->name('admin.')->group(function () use ($loginLimiter, $twoFactorLimiter) {
        Route::middleware('guest:admin')->group(function () use ($loginLimiter, $twoFactorLimiter) {
            Route::get('login', [AdminLoginController::class, 'create'])
                ->name('login');

            Route::post('login', [AdminLoginController::class, 'store'])
                ->middleware(array_filter([$loginLimiter ? 'throttle:'.$loginLimiter : null]))
                ->name('login.store');

            Route::get('two-factor-challenge', [AdminTwoFactorChallengeController::class, 'create'])
                ->name('two-factor.login');

            Route::post('two-factor-challenge', [AdminTwoFactorChallengeController::class, 'store'])
                ->middleware(array_filter([$twoFactorLimiter ? 'throttle:'.$twoFactorLimiter : null]))
                ->name('two-factor.login.store');
        });

        Route::middleware('auth:admin')->group(function () {
            Route::post('logout', AdminLogoutController::class)->name('logout');

            // The admin's own password confirmation. Laravel's one key,
            // `auth.password_confirmed_at`, once let a customer's confirmation
            // unlock an admin screen, back when the areas shared a session;
            // this one is keyed to the admin area and checks the password
            // against the admin guard.
            Route::get('user/confirm-password', [AdminConfirmPasswordController::class, 'show'])
                ->name('password.confirm');

            Route::post('user/confirm-password', [AdminConfirmPasswordController::class, 'store'])
                ->name('password.confirm.store');
        });
    });

    /*
    |----------------------------------------------------------------------
    | Frontend front door — /login
    |----------------------------------------------------------------------
    */

    Route::middleware('guest:web')->group(function () use ($loginLimiter) {
        Route::get('login', [LoginController::class, 'create'])
            ->name('login');

        Route::post('login', [AuthenticatedSessionController::class, 'store'])
            ->middleware(array_filter([$loginLimiter ? 'throttle:'.$loginLimiter : null]))
            ->name('login.store');
    });

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->middleware('auth:web')
        ->name('logout');

    // There is no public registration. See config/fortify.php for why.

    /*
    |----------------------------------------------------------------------
    | The frontend's credential flows
    |----------------------------------------------------------------------
    |
    | Fortify's own controllers, all on the frontend guard. The admin area has
    | its own two-factor challenge and password confirmation above.
    |
    | Fortify hardcodes the route names `login` and `two-factor.login` in a
    | few redirects, which is why the frontend's routes keep those names and
    | the admin's are prefixed.
    |
    */

    if (Features::enabled(Features::resetPasswords())) {
        Route::middleware('guest:web')->group(function () {
            Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
            Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
            Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
            Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.update');
        });
    }

    if (Features::enabled(Features::emailVerification())) {
        Route::middleware('auth:web')->group(function () use ($verificationLimiter) {
            Route::get('email/verify', EmailVerificationPromptController::class)->name('verification.notice');

            Route::get('email/verify/{id}/{hash}', VerifyEmailController::class)
                ->middleware(['signed', 'throttle:'.$verificationLimiter])
                ->name('verification.verify');

            Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
                ->middleware('throttle:'.$verificationLimiter)
                ->name('verification.send');
        });
    }

    Route::middleware('auth:web')->group(function () {
        Route::get('user/confirm-password', [ConfirmablePasswordController::class, 'show'])->name('password.confirm');
        Route::post('user/confirm-password', [ConfirmablePasswordController::class, 'store'])->name('password.confirm.store');
        Route::get('user/confirmed-password-status', [ConfirmedPasswordStatusController::class, 'show'])->name('password.confirmation');

        if (Features::enabled(Features::updateProfileInformation())) {
            Route::put('user/profile-information', [ProfileInformationController::class, 'update'])->name('user-profile-information.update');
        }

        if (Features::enabled(Features::updatePasswords())) {
            Route::put('user/password', [PasswordController::class, 'update'])->name('user-password.update');
        }
    });

    if (Features::enabled(Features::twoFactorAuthentication())) {
        Route::middleware('guest:web')->group(function () use ($twoFactorLimiter) {
            Route::get('two-factor-challenge', [TwoFactorAuthenticatedSessionController::class, 'create'])
                ->name('two-factor.login');

            Route::post('two-factor-challenge', [TwoFactorAuthenticatedSessionController::class, 'store'])
                ->middleware(array_filter([$twoFactorLimiter ? 'throttle:'.$twoFactorLimiter : null]))
                ->name('two-factor.login.store');
        });

        $twoFactorMiddleware = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword')
            ? ['auth:web', 'password.confirm']
            : ['auth:web'];

        Route::middleware($twoFactorMiddleware)->group(function () {
            Route::post('user/two-factor-authentication', [TwoFactorAuthenticationController::class, 'store'])->name('two-factor.enable');
            Route::delete('user/two-factor-authentication', [TwoFactorAuthenticationController::class, 'destroy'])->name('two-factor.disable');
            Route::post('user/confirmed-two-factor-authentication', [ConfirmedTwoFactorAuthenticationController::class, 'store'])->name('two-factor.confirm');
            Route::get('user/two-factor-qr-code', [TwoFactorQrCodeController::class, 'show'])->name('two-factor.qr-code');
            Route::get('user/two-factor-secret-key', [TwoFactorSecretKeyController::class, 'show'])->name('two-factor.secret-key');
            Route::get('user/two-factor-recovery-codes', [RecoveryCodeController::class, 'index'])->name('two-factor.recovery-codes');
            Route::post('user/two-factor-recovery-codes', [RecoveryCodeController::class, 'store'])->name('two-factor.regenerate-recovery-codes');
        });
    }

    if (Features::enabled(Features::passkeys())) {
        $throttle = $passkeyLimiter ? ['throttle:'.$passkeyLimiter] : [];

        $manageMiddleware = config('fortify-options.passkeys.confirmPassword', true)
            ? ['auth:web', 'password.confirm', ...$throttle]
            : ['auth:web', ...$throttle];

        Route::middleware(['guest:web', ...$throttle])->group(function () {
            Route::get('passkeys/login/options', [PasskeyLoginController::class, 'index'])->name('passkey.login-options');
            Route::post('passkeys/login', [PasskeyLoginController::class, 'store'])->name('passkey.login');
        });

        Route::middleware(['auth:web', ...$throttle])->group(function () {
            Route::get('passkeys/confirm/options', [PasskeyConfirmationController::class, 'index'])->name('passkey.confirm-options');
            Route::post('passkeys/confirm', [PasskeyConfirmationController::class, 'store'])->name('passkey.confirm');
        });

        Route::middleware($manageMiddleware)->group(function () {
            Route::get('user/passkeys/options', [PasskeyRegistrationController::class, 'index'])->name('passkey.registration-options');
            Route::post('user/passkeys', [PasskeyRegistrationController::class, 'store'])->name('passkey.store');
            Route::delete('user/passkeys/{passkey}', [PasskeyRegistrationController::class, 'destroy'])->name('passkey.destroy');
        });
    }
});
