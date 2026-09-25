<?php

namespace App\Admin\Http\Controllers;

use Illuminate\Contracts\View\View;

class DashboardController
{
    /**
     * Show the operational overview for the shop.
     */
    public function __invoke(): View
    {
        return view('admin::page.dashboard.index');
    }
}
