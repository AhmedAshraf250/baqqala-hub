<?php

namespace App\Admin\Http\Controllers;

use App\Foundation\Localization\LocalePreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The admin area's language switcher, including on its sign-in screen.
 *
 * Under `/admin` like everything else the admin posts, so the request opens
 * the admin session and its CSRF token. The choice itself is the reader's and
 * reaches the customer area too — see {@see LocalePreference}.
 */
class UpdateLocaleController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(LocalePreference::supported())],
        ]);

        LocalePreference::store($validated['locale']);

        return back();
    }
}
