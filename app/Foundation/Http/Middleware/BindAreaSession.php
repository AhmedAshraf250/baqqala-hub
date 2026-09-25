<?php

namespace App\Foundation\Http\Middleware;

use App\Foundation\Area\Area;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Points the session at this request's area before it is started.
 *
 * Laravel keeps one session per browser, which meant the two areas shared
 * every session key. A customer's password confirmation unlocked an admin
 * screen, and an admin's intended URL followed a customer through sign-in.
 * Giving each area its own cookie makes those keys unreachable rather than
 * merely renamed.
 *
 * This must run before `StartSession`, which reads `session.cookie` once.
 */
class BindAreaSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $area = Area::forSession($request);

        config(['session.cookie' => $area->sessionCookie()]);

        return $next($request);
    }
}
