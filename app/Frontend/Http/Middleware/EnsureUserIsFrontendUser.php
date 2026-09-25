<?php

namespace App\Frontend\Http\Middleware;

use App\Foundation\Area\Area;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The second barrier on the frontend's account pages.
 *
 * As with the admin area, the guard's model already hides the other area's
 * rows; this re-checks the login's area so no barrier stands alone, and runs
 * again on every Livewire update.
 */
class EnsureUserIsFrontendUser
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Area::Frontend->user()?->area === Area::Frontend, 403);

        return $next($request);
    }
}
