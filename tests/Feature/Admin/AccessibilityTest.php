<?php

use App\Admin\Http\Middleware\RequireAdminPasswordConfirmation;
use Dom\HTMLDocument;

/*
 * What the markup of each admin screen decides for a screen reader or a
 * keyboard: every link and button has a name, every field a label, and every
 * id a control points at is there. The rules are in accessibilityProblems().
 */

test('an admin screen names every control and labels every field', function (string $route) {
    actingAsAdmin();
    $this->withSession([RequireAdminPasswordConfirmation::SessionKey => time()]);

    $html = $this->get(route($route))->assertOk()->getContent();

    expect(accessibilityProblems($html))->toBe([]);
})->with(['admin.password.confirm', ...adminScreens()]);

test('every list AdminLTE would relabel already has a role and a label', function (string $route) {
    // AdminLTE's accessibility script gives any .nav or .navbar-nav without a
    // role role="navigation", which stops a list being a list, and labels it
    // "Navigation 1" in English on every page.
    actingAsAdmin();
    $this->withSession([RequireAdminPasswordConfirmation::SessionKey => time()]);

    $html = $this->get(route($route))->assertOk()->getContent();

    $lists = iterator_to_array(HTMLDocument::createFromString($html, LIBXML_NOERROR)->querySelectorAll('.nav, .navbar-nav'));

    expect($lists)->not->toBeEmpty();

    foreach ($lists as $list) {
        expect($list->getAttribute('role'))->not->toBeEmpty()
            ->and($list->getAttribute('aria-label'))->not->toBeEmpty();
    }
})->with(adminScreens());

test('the admin sign-in screen names every control and labels every field', function () {
    $html = $this->get(route('admin.login'))->assertOk()->getContent();

    expect(accessibilityProblems($html))->toBe([]);
});

test('a form control with an error is marked invalid and points at the message', function (string $control) {
    $html = (string) $this->withViewErrors(['price' => 'The price is required.'])
        ->blade("<x-admin::form.{$control} name=\"price\" label=\"Price\" />");

    $field = HTMLDocument::createFromString($html, LIBXML_NOERROR)->getElementById('price');

    expect(accessibilityProblems($html))->toBe([])
        ->and($field?->getAttribute('aria-invalid'))->toBe('true')
        ->and($field?->getAttribute('aria-describedby'))->toBe('price-error')
        ->and($html)->toMatch('/id="price-error"[^>]*>\s*The price is required\./');
})->with(['input', 'money', 'select', 'textarea', 'checkbox']);
