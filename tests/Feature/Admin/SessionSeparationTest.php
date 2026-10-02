<?php

use App\Foundation\Area\Area;
use App\Foundation\Http\Middleware\BindAreaSession;
use App\Foundation\Identity\Models\AdminUser;
use App\Foundation\Identity\Models\FrontendUser;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

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

test('a Livewire update that names its area opens that area\'s session, with no referrer at all', function (?string $referrer) {
    // The admin bundle names the area on every update. Before it did, a proxy
    // or privacy setting that stripped the referrer — or trimmed it to the
    // origin — sent every admin update to the customer session, and a 419.
    $request = Request::create('/livewire-abc/update', 'POST');
    $request->headers->set('X-Livewire', '1');
    $request->headers->set(Area::RequestHeader, 'admin');

    if ($referrer !== null) {
        $request->headers->set('referer', $referrer);
    }

    expect(Area::forSession($request))->toBe(Area::Admin);
})->with([
    'no referrer' => [null],
    'origin only' => ['http://localhost/'],
]);

test('the area header is read only on Livewire\'s own requests', function () {
    // Any other request is answered by its URL; a header cannot move a page
    // of the site into the admin session.
    $page = Request::create('/account');
    $page->headers->set(Area::RequestHeader, 'admin');

    expect(Area::forSession($page))->toBe(Area::Frontend);
});

test('an unknown area header falls back to the referrer', function () {
    $request = Request::create('/livewire-abc/update', 'POST');
    $request->headers->set('X-Livewire', '1');
    $request->headers->set(Area::RequestHeader, 'nonsense');
    $request->headers->set('referer', 'http://localhost/admin/settings');

    expect(Area::forSession($request))->toBe(Area::Admin);
});

test('the admin bundle sends the header the server reads', function () {
    // Two spellings of one name, in PHP and in JavaScript: this keeps them one.
    $script = (string) file_get_contents(resource_path('js/admin/modules/area-header.js'));

    expect($script)->toContain("const AREA_HEADER = '".Area::RequestHeader."'")
        ->toContain("const AREA = '".Area::Admin->value."'")
        ->and((string) file_get_contents(resource_path('js/admin/app.js')))->toContain('initAreaHeader()');
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

/**
 * Send the next request with exactly these cookies, the way a browser would,
 * to a session store built for that request — as it is in a real one. The
 * suite's array driver keeps one store for the whole test, so without this
 * both areas would share it and nothing here would be proved.
 *
 * @param  array<string, string>  $cookies
 */
function withOnlyTheseCookies(TestCase $test, array $cookies): void
{
    // The test case's own list of cookies to send, which it keeps to itself.
    (function (): void {
        $this->defaultCookies = [];
    })->call($test);

    foreach ($cookies as $name => $value) {
        $test->withCookie($name, $value);
    }

    app('session')->forgetDrivers();
}

test('each area really keeps its own session: one area\'s cookie signs no one in to the other', function () {
    // The barrier the rest of the separation stands on, driven end to end:
    // a real sign-in, the session stored in the database as in use, and the
    // cookie the browser was given sent back — to its own area, to the other
    // area, and under the other area's name.
    config(['session.driver' => 'database']);
    $administrator = AdminUser::factory()->create();

    withOnlyTheseCookies($this, []);
    $signIn = $this->post(route('admin.login.store'), ['email' => $administrator->email, 'password' => 'password']);
    $signIn->assertRedirect(route('admin.dashboard'));

    $adminCookie = $signIn->getCookie(Area::Admin->sessionCookie());

    // Signing in to the admin area wrote the admin cookie, and only that one.
    expect($adminCookie)->not->toBeNull()
        ->and($signIn->getCookie(Area::Frontend->sessionCookie()))->toBeNull();

    withOnlyTheseCookies($this, [Area::Admin->sessionCookie() => $adminCookie->getValue()]);
    $this->get(route('admin.dashboard'))->assertOk();

    // The same browser, on the site: the site reads its own cookie, which it
    // does not have, so this is a guest.
    withOnlyTheseCookies($this, [Area::Admin->sessionCookie() => $adminCookie->getValue()]);
    $this->get(route('frontend.account.dashboard'))->assertRedirect(route('login'));

    // Even the admin session itself, handed to the site under the site's
    // cookie name, holds no customer: each guard keeps its login under its
    // own key.
    withOnlyTheseCookies($this, [Area::Frontend->sessionCookie() => $adminCookie->getValue()]);
    $this->get(route('frontend.account.dashboard'))->assertRedirect(route('login'));
});

test('a customer\'s session cookie opens nothing in the admin area', function () {
    config(['session.driver' => 'database']);
    $customer = FrontendUser::factory()->create();

    withOnlyTheseCookies($this, []);
    $signIn = $this->post(route('login.store'), ['email' => $customer->email, 'password' => 'password']);
    $signIn->assertRedirect(route('frontend.account.dashboard'));

    $customerCookie = $signIn->getCookie(Area::Frontend->sessionCookie());

    expect($customerCookie)->not->toBeNull()
        ->and($signIn->getCookie(Area::Admin->sessionCookie()))->toBeNull();

    withOnlyTheseCookies($this, [Area::Frontend->sessionCookie() => $customerCookie->getValue()]);
    $this->get(route('frontend.account.dashboard'))->assertOk();

    // Under its own name, or under the admin area's: a guest at the admin door.
    withOnlyTheseCookies($this, [Area::Frontend->sessionCookie() => $customerCookie->getValue()]);
    $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));

    withOnlyTheseCookies($this, [Area::Admin->sessionCookie() => $customerCookie->getValue()]);
    $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
});
