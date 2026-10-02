<?php

use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\AdminUser;
use App\Foundation\Identity\Models\FrontendUser;
use App\Foundation\Localization\LocalePreference;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('choosing a language in the admin area remembers it in that area\'s cookie', function () {
    actingAsAdmin();

    $this->from(route('admin.dashboard'))
        ->post(route('admin.locale.update'), ['locale' => 'en'])
        ->assertRedirect(route('admin.dashboard'))
        ->assertCookie(Area::Admin->localeCookie(), 'en')
        ->assertCookieMissing(Area::Frontend->localeCookie());
});

test('anyone reading the site can choose its language, signed in or not', function () {
    $this->from(route('frontend.home'))
        ->post(route('frontend.locale.update'), ['locale' => 'en'])
        ->assertRedirect(route('frontend.home'))
        ->assertCookie(Area::Frontend->localeCookie(), 'en')
        ->assertCookieMissing(Area::Admin->localeCookie());
});

test('the language chosen in one area is not the language of the other', function (Area $chosenIn, string $other) {
    // The defect this closes: one cookie served both areas, so a member of
    // staff choosing English in the back office turned the site English for
    // the customer who used the same browser next.
    $this->withCookie($chosenIn->localeCookie(), 'en')
        ->get(route($other))
        ->assertOk()
        ->assertSee('lang="ar"', escape: false);
})->with([
    'admin, read on the site' => [Area::Admin, 'frontend.home'],
    'site, read in the admin' => [Area::Frontend, 'admin.login'],
]);

test('each area renders in the language chosen in it', function (string $locale) {
    readingIn($locale);

    $this->get(route('frontend.home'))->assertSee('lang="'.$locale.'"', escape: false);
    $this->get(route('admin.login'))->assertSee('lang="'.$locale.'"', escape: false);
})->with(['ar', 'en']);

test('a signed-in reader\'s choice is kept on their login', function () {
    $customer = actingAsCustomer();

    $this->post(route('frontend.locale.update'), ['locale' => 'en']);

    expect($customer->fresh()->locale)->toBe('en')
        ->and($customer->fresh()->preferredLocale())->toBe('en');
});

test('an administrator\'s choice is kept on their login, not on a customer\'s', function () {
    $administrator = actingAsAdmin();
    $customer = actingAsCustomer();

    $this->post(route('admin.locale.update'), ['locale' => 'en']);

    expect($administrator->fresh()->locale)->toBe('en')
        ->and($customer->fresh()->locale)->toBeNull();
});

test('a login\'s choice follows it to a browser that has never chosen', function () {
    $customer = FrontendUser::factory()->create();
    $customer->forceFill(['locale' => 'en'])->save();
    actingAsCustomer($customer);

    $this->get(route('frontend.account.dashboard'))
        ->assertOk()
        ->assertSee('lang="en"', escape: false);
});

test('a login\'s choice outranks the browser\'s', function () {
    $administrator = AdminUser::factory()->create();
    $administrator->forceFill(['locale' => 'en'])->save();
    actingAsAdmin($administrator);

    $this->withCookie(Area::Admin->localeCookie(), 'ar')
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('lang="en"', escape: false);
});

test('the admin sign-in screen can switch language before anyone signs in', function () {
    $this->from(route('admin.login'))
        ->post(route('admin.locale.update'), ['locale' => 'en'])
        ->assertRedirect(route('admin.login'))
        ->assertCookie(Area::Admin->localeCookie(), 'en');
});

test('an unsupported language is refused and never stored', function (string $route, Area $area) {
    $this->from(route('frontend.home'))
        ->post(route($route), ['locale' => 'xx'])
        ->assertSessionHasErrors('locale')
        ->assertCookieMissing($area->localeCookie());
})->with([
    'admin' => ['admin.locale.update', Area::Admin],
    'frontend' => ['frontend.locale.update', Area::Frontend],
]);

test('a tampered or unknown cookie falls back to the area\'s default', function () {
    $this->withCookie(Area::Frontend->localeCookie(), 'xx')
        ->get(route('frontend.home'))
        ->assertOk()
        ->assertSee('lang="'.LocalePreference::defaultFor(Area::Frontend).'"', escape: false);
});

test('each area may open in a language of its own', function () {
    config(['foundation.locales.areas.frontend.default' => 'en']);

    $this->get(route('frontend.home'))->assertSee('lang="en"', escape: false);
    $this->get(route('admin.login'))->assertSee('lang="ar"', escape: false);
});

test('an area without a default of its own opens in the application\'s', function () {
    config(['foundation.locales.areas.admin.default' => 'xx']);

    expect(LocalePreference::defaultFor(Area::Admin))->toBe(config('foundation.locales.default'));
});

test('the browser\'s language decides only where the area allows it', function () {
    $englishBrowser = ['HTTP_ACCEPT_LANGUAGE' => 'en-GB,en;q=0.9'];

    $this->get(route('frontend.home'), $englishBrowser)->assertSee('lang="ar"', escape: false);

    config(['foundation.locales.areas.frontend.negotiate' => true]);

    $this->get(route('frontend.home'), $englishBrowser)->assertSee('lang="en"', escape: false);
    $this->get(route('admin.login'), $englishBrowser)->assertSee('lang="ar"', escape: false);
});

test('a browser asking only for languages the application lacks gets the default', function () {
    config(['foundation.locales.areas.frontend.negotiate' => true]);

    $this->get(route('frontend.home'), ['HTTP_ACCEPT_LANGUAGE' => 'fr-FR,de;q=0.8'])
        ->assertSee('lang="ar"', escape: false);
});

test('a choice made in the browser outranks the browser\'s language', function () {
    config(['foundation.locales.areas.frontend.negotiate' => true]);

    $this->withCookie(Area::Frontend->localeCookie(), 'ar')
        ->get(route('frontend.home'), ['HTTP_ACCEPT_LANGUAGE' => 'en'])
        ->assertSee('lang="ar"', escape: false);
});

test('the site offers its switcher on every public face', function (string $route) {
    $this->get(route($route))
        ->assertOk()
        ->assertSee('data-test="locale-switcher"', escape: false)
        ->assertSee(route('frontend.locale.update'), escape: false);
})->with(['frontend.home', 'login', 'password.request']);
