<?php

use App\Foundation\Localization\LocalePreference;

/*
 * What the markup of each frontend screen decides for a screen reader or a
 * keyboard: every link and button has a name, every field a label, and every
 * id a control points at is there. The rules are in accessibilityProblems().
 */

test('a public screen names every control and labels every field', function (string $route, array $parameters) {
    $html = $this->get(route($route, $parameters))->assertOk()->getContent();

    expect(accessibilityProblems($html))->toBe([]);
})->with([
    'home' => ['frontend.home', []],
    'sign-in' => ['login', []],
    'forgotten password' => ['password.request', []],
    'reset password' => ['password.reset', ['token' => 'a-reset-token']],
]);

test('a customer screen names every control and labels every field', function (string $route) {
    actingAsCustomer();
    $this->withSession(['auth.password_confirmed_at' => time()]);

    $html = $this->get(route($route))->assertOk()->getContent();

    expect(accessibilityProblems($html))->toBe([]);
})->with(['password.confirm', ...customerScreens()]);

test('the sidebar buttons are named in the reader\'s language', function () {
    // Flux names them "Toggle sidebar" in English, whatever the locale.
    actingAsCustomer();

    $html = $this->withCookie(LocalePreference::Cookie, 'ar')
        ->get(route('frontend.account.dashboard'))
        ->assertOk()
        ->getContent();

    expect($html)->not->toContain('Toggle sidebar')
        ->and(substr_count($html, __('shell.actions.toggle_sidebar', locale: 'ar')))->toBe(2);
});
