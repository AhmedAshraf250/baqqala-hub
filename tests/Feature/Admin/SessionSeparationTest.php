<?php

use App\Foundation\Area\Area;
use App\Foundation\Http\Middleware\BindAreaSession;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

uses(LazilyRefreshDatabase::class);

test('each area stores its session in its own cookie', function () {
    expect(Area::Admin->sessionCookie())->toBe(config('session.cookie').'_admin')
        ->and(Area::Frontend->sessionCookie())->toBe(config('session.cookie'))
        ->and(Area::Admin->sessionCookie())->not->toBe(Area::Frontend->sessionCookie());
});

test('an admin url opens the admin session', function (string $path) {
    expect(Area::forSession(Request::create($path)))->toBe(Area::Admin);
})->with(['/admin', '/admin/dashboard', '/admin/settings/security', '/admin/login']);

test('any other url opens the customer session', function (string $path) {
    expect(Area::forSession(Request::create($path)))->toBe(Area::Frontend);
})->with(['/', '/login', '/account', '/register', '/administrators']);

test('an area-neutral endpoint follows the page it was called from', function () {
    // Livewire posts every component update to one application-wide endpoint
    // outside /admin, so the URL alone cannot say which session to open.
    $fromAdmin = Request::create('/livewire-abc/update', 'POST');
    $fromAdmin->headers->set('referer', 'http://localhost/admin/settings/profile');

    $fromCustomer = Request::create('/livewire-abc/update', 'POST');
    $fromCustomer->headers->set('referer', 'http://localhost/account/settings/profile');

    expect(Area::forSession($fromAdmin))->toBe(Area::Admin)
        ->and(Area::forSession($fromCustomer))->toBe(Area::Frontend);
});

test('a missing or unreadable referrer falls back to the customer session', function (?string $referrer) {
    // Failing towards the customer area is the safe direction: an admin
    // request that cannot be placed loses its session and is bounced to the
    // admin front door, rather than a customer request reaching admin state.
    $request = Request::create('/livewire-abc/update', 'POST');

    if ($referrer !== null) {
        $request->headers->set('referer', $referrer);
    }

    expect(Area::forSession($request))->toBe(Area::Frontend);
})->with([null, '', 'not a url', 'http://evil.test/administrator']);

test('serving an admin route binds the admin session', function () {
    // The suite runs the array session driver, which does not emit a session
    // cookie, so the observable effect is which cookie the request bound to.
    actingAsAdmin();

    $this->get(route('admin.dashboard'))->assertOk();

    expect(config('session.cookie'))->toBe(Area::Admin->sessionCookie());
});

test('serving a customer route binds the customer session', function () {
    actingAsCustomer();

    $this->get(route('frontend.account.dashboard'))->assertOk();

    expect(config('session.cookie'))->toBe(Area::Frontend->sessionCookie());
});

test('binding the session rewrites the cookie without compounding it', function () {
    // Called twice in one lifecycle, the derivation must be stable: reading
    // the mutated `session.cookie` produced `..._admin_admin`.
    $middleware = new BindAreaSession;
    $next = fn ($request) => new Response;

    $middleware->handle(Request::create('/admin/dashboard'), $next);
    $middleware->handle(Request::create('/admin/dashboard'), $next);

    expect(config('session.cookie'))->toBe(config('session.base_cookie').'_admin');
});
