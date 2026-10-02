<?php

namespace App\Foundation\Area;

use App\Foundation\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The two halves of the application, and which one a login belongs to.
 *
 * The admin area is the back office. The frontend is everything the public
 * faces: the site anyone can open, and the account pages a signed-in visitor
 * reaches under `/account` — for this product, a customer; for a school, a
 * parent. It is named for the surface, not for who uses it, so it survives a
 * change of product.
 *
 * "Which area is this?" comes up in routing, authentication, sessions, and
 * redirects. Answering it here, once, is what keeps the halves from leaking
 * into each other — nothing else tests a URL prefix by hand. It is also the
 * `users.area` column: a login belongs to exactly one area.
 */
enum Area: string
{
    case Admin = 'admin';

    case Frontend = 'frontend';

    /**
     * The area a request belongs to, decided by its URL.
     */
    public static function fromRequest(Request $request): self
    {
        return $request->is('admin', 'admin/*') ? self::Admin : self::Frontend;
    }

    /**
     * The header an area's script adds to every Livewire request it sends.
     */
    public const RequestHeader = 'X-Area';

    /**
     * The area whose session a request should open.
     *
     * Every request is answered by its own URL except Livewire's: it posts
     * every component update to a single application-wide endpoint outside
     * `/admin`. For those, the page says which area it belongs to — the admin
     * bundle adds {@see self::RequestHeader} to each update — and failing that,
     * the page the request came from does. The referrer alone was not enough:
     * a proxy or a privacy setting that strips it, or trims it to the origin,
     * sent every admin update to the frontend session and a 419. File uploads
     * are sent outside Livewire's request pipeline, so they still rely on it.
     *
     * Either answer only picks which session cookie to open — it is not an
     * authorisation decision. The cookie is still the visitor's own, signed and
     * encrypted, and the guard still decides who they are.
     */
    public static function forSession(Request $request): self
    {
        if ($request->is('admin', 'admin/*')) {
            return self::Admin;
        }

        if ($request->hasHeader('X-Livewire')) {
            $declared = self::tryFrom((string) $request->headers->get(self::RequestHeader));

            if ($declared !== null) {
                return $declared;
            }
        }

        $referrer = $request->headers->get('referer');

        if ($referrer === null) {
            return self::Frontend;
        }

        $path = trim((string) parse_url($referrer, PHP_URL_PATH), '/');

        return $path === 'admin' || str_starts_with($path, 'admin/')
            ? self::Admin
            : self::Frontend;
    }

    /**
     * The session cookie this area stores its session in.
     *
     * Separate cookies mean separate sessions: a key written by one area — the
     * intended URL, a flash message, a password confirmation — is not merely
     * namespaced away from the other area, it is invisible to it.
     */
    public function sessionCookie(): string
    {
        // Read the immutable base, not `session.cookie`, which BindAreaSession
        // rewrites on every request.
        $base = config('session.base_cookie', config('session.cookie'));

        return match ($this) {
            self::Admin => $base.'_admin',
            self::Frontend => $base,
        };
    }

    /**
     * The cookie this area remembers its reader's language in.
     *
     * Separate for the same reason the sessions are: the shop's staff and its
     * customers are different readers, and choosing English in the back office
     * must not turn the site English for whoever opens it next.
     */
    public function localeCookie(): string
    {
        return match ($this) {
            self::Admin => 'locale_admin',
            self::Frontend => 'locale',
        };
    }

    /**
     * The authentication guard this area signs in through.
     *
     * The two guards use different session keys and different providers, so a
     * session minted for one area is unresolvable in the other.
     */
    public function guard(): string
    {
        return match ($this) {
            self::Admin => 'admin',
            self::Frontend => 'web',
        };
    }

    /**
     * Whoever is signed in to this area, asked of this area's own guard.
     *
     * The default guard is the frontend's, so admin code reaching for
     * `Auth::user()` would be relying on middleware having switched it. Asking
     * the area removes the question.
     */
    public function user(): ?User
    {
        $user = Auth::guard($this->guard())->user();

        return $user instanceof User ? $user : null;
    }

    /**
     * The sign-in screen for this area.
     */
    public function loginRoute(): string
    {
        return match ($this) {
            self::Admin => 'admin.login',
            self::Frontend => 'login',
        };
    }

    /**
     * The landing page for this area.
     */
    public function homeRoute(): string
    {
        return match ($this) {
            self::Admin => 'admin.dashboard',
            self::Frontend => 'frontend.account.dashboard',
        };
    }

    /**
     * The translated name of this area.
     */
    public function label(): string
    {
        return __('foundation.areas.'.$this->value);
    }
}
