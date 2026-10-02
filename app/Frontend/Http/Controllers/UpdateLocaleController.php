<?php

namespace App\Frontend\Http\Controllers;

use App\Foundation\Area\Area;
use App\Foundation\Localization\LocalePreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The frontend's language switcher, for anyone reading the site — signed in
 * or not. The choice is the frontend's alone — see {@see LocalePreference}.
 */
class UpdateLocaleController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(LocalePreference::supported())],
        ]);

        LocalePreference::remember(Area::Frontend, $validated['locale']);

        return back();
    }
}
