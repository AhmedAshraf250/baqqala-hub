<?php

use App\Admin\Http\Middleware\RequireAdminPasswordConfirmation;

/*
 * What only a browser sees: contrast, the accessibility tree once scripts have
 * run, script errors, and the page's real width. Each screen is checked in
 * both languages and both colour modes. The markup-only rules run faster in
 * tests/Feature, through accessibilityProblems().
 */

dataset('languages', ['ar', 'en']);
dataset('colour modes', ['light', 'dark']);

/**
 * Open a path the way a reader in that language and colour mode sees it.
 */
function openAs(string $path, string $language, string $mode): mixed
{
    readingIn($language);

    $page = visit($path);

    return $mode === 'dark' ? $page->inDarkMode() : $page->inLightMode();
}

test('an admin screen passes axe with no script errors', function (string $route, string $language, string $mode) {
    actingAsAdmin();
    $this->withSession([RequireAdminPasswordConfirmation::SessionKey => time()]);

    openAs(route($route, absolute: false), $language, $mode)
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
})->with(adminScreens())->with('languages')->with('colour modes');

test('a public screen passes axe with no script errors', function (string $route, string $language, string $mode) {
    openAs(route($route, absolute: false), $language, $mode)
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
})->with(['frontend.home', 'login', 'password.request', 'admin.login'])->with('languages')->with('colour modes');

test('a screen fits a 375px phone without scrolling sideways', function (string $route, ?string $visitor, string $language) {
    match ($visitor) {
        'admin' => actingAsAdmin(),
        'customer' => actingAsCustomer(),
        default => null,
    };

    $this->withSession([
        RequireAdminPasswordConfirmation::SessionKey => time(),
        'auth.password_confirmed_at' => time(),
    ]);
    readingIn($language);

    $overflow = visit(route($route, absolute: false))->on()->mobile()
        ->script('() => document.documentElement.scrollWidth - document.documentElement.clientWidth');

    expect($overflow)->toBeInt()->toBeLessThanOrEqual(0);
})->with([
    ...array_map(fn (string $route): array => [$route, 'admin'], adminScreens()),
    ...array_map(fn (string $route): array => [$route, 'customer'], customerScreens()),
    ['frontend.home', null],
    ['login', null],
    ['password.request', null],
    ['admin.login', null],
])->with('languages');

test('a customer screen passes axe with no script errors', function (string $route, string $language, string $mode) {
    actingAsCustomer();
    $this->withSession(['auth.password_confirmed_at' => time()]);

    openAs(route($route, absolute: false), $language, $mode)
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
})->with(customerScreens())->with('languages')->with('colour modes');
