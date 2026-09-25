<?php

use App\Foundation\Identity\Models\User;
use App\Foundation\Localization\LocalePreference;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('guests are redirected to the login page', function () {
    $this->get(route('frontend.account.dashboard'))->assertRedirect(route('login'));
});

test('a signed-in customer can visit their dashboard', function () {
    $this->actingAs(User::factory()->frontend()->create());

    $this->get(route('frontend.account.dashboard'))->assertOk();
});

test('the account page is named as the visitor\'s own, in every language', function (string $locale) {
    // The frontend is the whole public face; `/account` is the part that is
    // the signed-in visitor's own, and says so.
    actingAsCustomer();
    app()->setLocale($locale);

    $this->withCookie(LocalePreference::Cookie, $locale)
        ->get(route('frontend.account.dashboard'))
        ->assertOk()
        ->assertSee(__('shell.page.account.title'))
        ->assertDontSee('shell.page.');
})->with(['ar', 'en']);

test('the public site opens without signing in', function () {
    $this->get(route('frontend.home'))
        ->assertOk()
        ->assertSee(__('shell.page.home.heading'));
});
