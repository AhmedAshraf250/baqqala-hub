<?php

namespace App\Admin\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Serves a section that is routed and navigable but not built yet.
 *
 * Routing the product's whole shape up front is deliberate: the sidebar shows
 * what the system is going to be, links never 404, and each section says what
 * it will hold. Building one means pointing its route at a real controller —
 * nothing in the navigation or the breadcrumbs changes.
 *
 * The route names the description as a translation key, so a module's planned
 * screens describe themselves in the module's own strings:
 * `->defaults('description', '{module}::module.planned.{screen}')`.
 */
class PlannedSectionController
{
    public function __invoke(Request $request): View
    {
        return view('admin::page.planned.index', [
            'description' => (string) $request->route()?->defaults['description'],
        ]);
    }
}
