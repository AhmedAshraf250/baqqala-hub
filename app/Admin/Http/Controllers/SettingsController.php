<?php

namespace App\Admin\Http\Controllers;

use App\Admin\Settings\AdminSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * The settings screen: its tabs, and which one is open.
 *
 * A controller rather than `Route::view()`: the latter lost the admin guard's
 * session on this route and bounced a signed-in admin to the sign-in screen,
 * and a controller is route-cacheable and testable besides.
 */
class SettingsController
{
    public function __invoke(Request $request, AdminSettings $settings): View
    {
        return view('admin::page.settings.index', [
            'sections' => $settings->sections(),
            'active' => $settings->resolveActive($request->string('tab')->toString() ?: null),
        ]);
    }
}
