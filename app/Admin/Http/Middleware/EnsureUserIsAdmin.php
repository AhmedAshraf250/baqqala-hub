<?php

namespace App\Admin\Http\Middleware;

use App\Foundation\Area\Area;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The second barrier on the admin area.
 *
 * The first is the separate session cookie; the second is the guard itself —
 * `auth:admin` resolves through a model whose scope hides every customer row,
 * so a customer session has no user here. This re-checks the login's area
 * anyway, because a single line of defence around the shop's money is one line
 * too few.
 *
 * Registered as persistent Livewire middleware, so it runs again on every
 * component update and not only when the page first loads.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Area::Admin->user()?->area === Area::Admin, 403);

        return $next($request);
    }
}
