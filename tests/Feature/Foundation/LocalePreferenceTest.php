<?php

use App\Foundation\Localization\LocalePreference;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('choosing a language remembers it for the reader, in its own cookie', function () {
    actingAsAdmin();

    $this->from(route('admin.dashboard'))
        ->post(route('admin.locale.update'), ['locale' => 'en'])
        ->assertRedirect(route('admin.dashboard'))
        ->assertCookie(LocalePreference::Cookie, 'en');
});

test('the language chosen in one area is the language of the other', function () {
    // The defect this closes: the choice lived in the session, and each area
    // has its own session, so choosing English in the admin area left the
    // portal in Arabic. The preference now belongs to the reader.
    actingAsCustomer();

    $this->withCookie(LocalePreference::Cookie, 'en')
        ->get(route('frontend.account.dashboard'))
        ->assertOk()
        ->assertSee('lang="en"', escape: false);

    $this->withCookie(LocalePreference::Cookie, 'ar')
        ->get(route('frontend.account.dashboard'))
        ->assertOk()
        ->assertSee('lang="ar"', escape: false);
});

test('the admin sign-in screen can switch language before anyone signs in', function () {
    $this->from(route('admin.login'))
        ->post(route('admin.locale.update'), ['locale' => 'en'])
        ->assertRedirect(route('admin.login'))
        ->assertCookie(LocalePreference::Cookie, 'en');
});

test('an unsupported language is refused and never stored', function () {
    $this->from(route('admin.login'))
        ->post(route('admin.locale.update'), ['locale' => 'xx'])
        ->assertSessionHasErrors('locale')
        ->assertCookieMissing(LocalePreference::Cookie);
});

test('a tampered or unknown cookie falls back to the default language', function () {
    actingAsCustomer();

    $this->withCookie(LocalePreference::Cookie, 'xx')
        ->get(route('frontend.account.dashboard'))
        ->assertOk()
        ->assertSee('lang="'.config('foundation.locales.default').'"', escape: false);
});
