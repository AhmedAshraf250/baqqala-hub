<?php

namespace App\Foundation\Http\Middleware;

use App\Foundation\Localization\LocalePreference;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the visitor's chosen language to the request.
 *
 * This runs for both areas: direction, the AdminLTE stylesheet, and every
 * translated string are downstream of it.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale(LocalePreference::resolve($request));

        return $next($request);
    }
}
