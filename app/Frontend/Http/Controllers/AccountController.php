<?php

namespace App\Frontend\Http\Controllers;

use Illuminate\Contracts\View\View;

class AccountController
{
    /**
     * The signed-in visitor's account overview, at /account.
     */
    public function __invoke(): View
    {
        return view('frontend::page.account.index');
    }
}
