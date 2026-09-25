<?php

namespace App\Frontend\Http\Controllers;

use App\Foundation\Area\Area;
use Illuminate\Contracts\View\View;

/**
 * The site's front page, open to anyone.
 *
 * It offers a signed-in visitor their account and anyone else the sign-in
 * screen, asked of the frontend's own guard: an administrator signed in to
 * the back office is nobody here.
 */
class HomeController
{
    public function __invoke(): View
    {
        return view('frontend::page.home', [
            'signedIn' => Area::Frontend->user() !== null,
        ]);
    }
}
