<?php

namespace App\Foundation\Http\Middleware;

use App\Foundation\Area\Area;
use App\Foundation\Localization\LocalePreference;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the reader's language in this area to the request.
 *
 * This runs for both areas: direction, the AdminLTE stylesheet, and every
 * translated string are downstream of it. The area is the one whose session
 * the request opened, so a Livewire update speaks the language of the page
 * it came from.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale(LocalePreference::resolve($request, Area::forSession($request)));

        return $next($request);
    }
}
