<?php

use App\Foundation\Http\Middleware\PreventRequestForgeryWithoutCookie;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Session\TokenMismatchException;

uses(LazilyRefreshDatabase::class);

/*
 * The CSRF token lives in the session, and each area has its own session, so
 * each area has its own token. Laravel also copied it into an `XSRF-TOKEN`
 * cookie for JavaScript — one cookie for the whole site, holding whichever
 * area's token was issued last. Script reads the page's own token instead,
 * and the cookie is no longer sent.
 */

test('every page carries its own session\'s token', function (string $route) {
    $html = $this->get(route($route))->assertOk()->getContent();

    expect($html)->toContain('<meta name="csrf-token" content="'.csrf_token().'"');
})->with(['frontend.home', 'login', 'password.request', 'admin.login']);

test('a signed-in customer\'s pages carry it too', function () {
    actingAsCustomer();

    $html = $this->get(route('frontend.account.dashboard'))->assertOk()->getContent();

    expect($html)->toContain('<meta name="csrf-token" content="'.csrf_token().'"');
});

test('no area sends the site-wide XSRF-TOKEN cookie', function (string $route) {
    $this->get(route($route))->assertOk()->assertCookieMissing('XSRF-TOKEN');
})->with(['frontend.home', 'admin.login']);

test('a token from one area\'s session is refused by the other\'s', function () {
    // The guarantee the separate sessions give: a form or script holding the
    // admin area's token cannot post into a customer's session, nor the other
    // way round. (The suite skips CSRF checks, so the middleware is asked
    // directly, as a browser that does not vouch for the origin would be.)
    $adminSession = new Store('admin', new ArraySessionHandler(120));
    $customerSession = new Store('customer', new ArraySessionHandler(120));
    $adminSession->start();
    $customerSession->start();

    $request = Request::create('/account/settings/profile', 'POST', ['_token' => $adminSession->token()]);
    $request->setLaravelSession($customerSession);

    $middleware = new class(app(), app('encrypter')) extends PreventRequestForgeryWithoutCookie
    {
        protected function runningUnitTests(): bool
        {
            return false;
        }
    };

    expect(fn () => $middleware->handle($request, fn () => response('reached')))
        ->toThrow(TokenMismatchException::class);
});
