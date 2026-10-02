<?php

namespace App\Foundation\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

/**
 * Laravel's CSRF protection, without its `XSRF-TOKEN` cookie.
 *
 * The token itself lives in the session, and each area has its own session,
 * so each area already has its own token. The cookie was only a copy of it
 * for JavaScript to read — one cookie for the whole site, overwritten by
 * whichever area answered last. Nothing here needs it: Livewire and the
 * passkey script read the token from the page's `<meta name="csrf-token">`,
 * which every layout carries.
 */
class PreventRequestForgeryWithoutCookie extends PreventRequestForgery
{
    /**
     * @var bool
     */
    protected $addHttpCookie = false;
}
