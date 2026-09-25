<?php

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('a customer screen renders', function (string $route) {
    actingAsCustomer();

    // The security screen sits behind password confirmation.
    $this->withSession(['auth.password_confirmed_at' => time()]);

    $this->get(route($route))->assertOk();
})->with(customerScreens());

test('a customer screen renders its shell exactly once', function (string $route) {
    actingAsCustomer();

    $this->withSession(['auth.password_confirmed_at' => time()]);

    $html = $this->get(route($route))->assertOk()->getContent();

    expect(substr_count($html, '<html'))->toBe(1, "[{$route}] rendered more than one document.");
})->with(customerScreens());

test('a customer screen never renders the admin shell', function (string $route) {
    actingAsCustomer();

    $this->withSession(['auth.password_confirmed_at' => time()]);

    $this->get(route($route))
        ->assertOk()
        ->assertDontSee('app-wrapper', false)
        ->assertDontSee('app-sidebar', false);
})->with(customerScreens());
