<?php

namespace App\Admin\Http\Middleware;

use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Symfony\Component\HttpFoundation\Response;

/**
 * Password confirmation, scoped to the admin area.
 *
 * Laravel's own `password.confirm` keeps one timestamp under
 * `auth.password_confirmed_at`. Back when both areas shared a session, that let
 * a *customer* confirming their password unlock an *admin* screen. The sessions
 * are separate now, and this stays anyway: the admin's confirmation is checked
 * against the admin guard, under a key only the admin area writes.
 *
 * Registered as persistent Livewire middleware. Without that, a component on a
 * confirmed screen kept accepting actions after the confirmation expired,
 * because Livewire's updates only re-run the middleware it is told to.
 */
class RequireAdminPasswordConfirmation
{
    /**
     * The session key holding the admin's most recent confirmation.
     *
     * Deliberately outside Laravel's `auth.*` namespace: writing to
     * `auth.password_confirmed_at.admin` would turn Laravel's own scalar key
     * into an array and break the customer area's confirmation with a 500.
     */
    public const SessionKey = 'shell.admin.password_confirmed_at';

    public function handle(Request $request, Closure $next, ?int $timeout = null): Response
    {
        if ($this->needsConfirmation($request, $timeout)) {
            // Thrown, not returned. Livewire re-runs this on every component
            // update but only acts on a redirect it gets back — a returned
            // JSON refusal was dropped and the action ran anyway. An exception
            // cannot be dropped.
            throw new HttpResponseException($request->expectsJson()
                ? response()->json(['message' => __('shell.auth.confirm_password_message')], 423)
                : redirect()->guest(route('admin.password.confirm')));
        }

        return $next($request);
    }

    /**
     * Record that the signed-in administrator just proved their password.
     */
    public static function grant(Request $request): void
    {
        $request->session()->put(self::SessionKey, Date::now()->unix());
    }

    /**
     * Drop any confirmation, so the next protected screen asks again.
     */
    public static function revoke(Request $request): void
    {
        $request->session()->forget(self::SessionKey);
    }

    /**
     * Whether the admin must prove their password again.
     */
    private function needsConfirmation(Request $request, ?int $timeout): bool
    {
        $confirmedAt = $request->session()->get(self::SessionKey, 0);

        $timeout ??= (int) config('auth.password_timeout', 10800);

        return (Date::now()->unix() - $confirmedAt) > $timeout;
    }
}
