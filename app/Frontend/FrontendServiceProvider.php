<?php

namespace App\Frontend;

use App\Foundation\Identity\Validation\PasswordRules;
use App\Frontend\Actions\ResetUserPassword;
use App\Frontend\Http\Middleware\EnsureUserIsFrontendUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;
use Livewire\Livewire;

/**
 * The frontend's wiring: its view components, its sign-in screens, and its
 * middleware's reach into Livewire.
 *
 * Fortify belongs here: it serves the frontend only, on the `web` guard,
 * whose model cannot see an administrator's login. The admin area signs in on
 * its own guard, in `App\Admin\Http\Controllers\Auth`.
 */
final class FrontendServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Every auth route is declared in routes/auth.php, where it can be read
        // and guarded. Fortify must not register its own copies.
        Fortify::ignoreRoutes();
    }

    public function boot(): void
    {
        // `<x-frontend::ui.user-menu>` is the class App\Frontend\View\Components\Ui\UserMenu
        // when there is one, and the template alone when there is not.
        Blade::componentNamespace('App\\Frontend\\View\\Components', 'frontend');

        $this->registerFortifyScreens();

        // See AdminServiceProvider: Livewire updates re-run only the
        // middleware they are told to.
        Livewire::addPersistentMiddleware([EnsureUserIsFrontendUser::class]);
    }

    /**
     * The sign-in screens Fortify's own controllers render. `/login` is not
     * one of them: it has a controller of its own.
     *
     * Each closure is the controller of its screen as far as the view is
     * concerned: whatever the page shows is handed to it here, so the
     * template never reaches for the request or the session itself.
     */
    private function registerFortifyScreens(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Fortify::twoFactorChallengeView(fn () => view('frontend::auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => view('frontend::auth.confirm-password'));
        Fortify::verifyEmailView(fn (Request $request) => view('frontend::auth.verify-email', [
            'linkSent' => $request->session()->get('status') === 'verification-link-sent',
        ]));
        Fortify::resetPasswordView(fn (Request $request) => view('frontend::auth.reset-password', [
            'token' => $request->route('token'),
            'email' => $request->string('email')->toString(),
            'passwordRules' => PasswordRules::forPasswordManager(),
        ]));
        Fortify::requestPasswordResetLinkView(fn () => view('frontend::auth.forgot-password'));
    }
}
