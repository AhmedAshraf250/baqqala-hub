<?php

use App\Admin\Http\Middleware\RequireAdminPasswordConfirmation;
use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\FrontendUser;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('an admin screen behind confirmation sends the admin to the admin confirmation screen', function () {
    actingAsAdmin();

    // Not Fortify's /user/confirm-password, which lives on the customer guard
    // and would bounce a signed-in admin to the customer login screen.
    $this->get(route('admin.access.administrators.index'))
        ->assertRedirect(route('admin.password.confirm'));
});

test('the admin confirmation screen renders in the admin shell', function () {
    actingAsAdmin();

    $this->get(route('admin.password.confirm'))
        ->assertOk()
        ->assertSee('login-box', false)
        ->assertSee(route('admin.password.confirm.store'), false)
        ->assertDontSee('flux', false);
});

test('a customer confirming their password does not unlock an admin screen', function () {
    // The leak this covers, from when the areas shared a session: Laravel
    // keeps a single `auth.password_confirmed_at` for every guard. The areas
    // now have separate sessions; this suite runs both in one (the array
    // driver), which is exactly the case the separate key must still cover.
    actingAsAdmin();
    $this->actingAs(FrontendUser::factory()->create(), Area::Frontend->guard());

    // The customer proves their password on their own screen.
    $this->post(route('password.confirm.store'), ['password' => 'password'])
        ->assertSessionHasNoErrors();

    // The admin screen must still be locked.
    $this->get(route('admin.access.administrators.index'))
        ->assertRedirect(route('admin.password.confirm'));
});

test('an admin confirming their password does not unlock a customer screen', function () {
    actingAsAdmin();
    $this->actingAs(FrontendUser::factory()->create(), Area::Frontend->guard());

    $this->post(route('admin.password.confirm.store'), ['password' => 'password'])
        ->assertSessionHasNoErrors();

    $this->get(route('frontend.account.settings.security'))
        ->assertRedirect(route('password.confirm'));
});

test('the admin confirmation checks the password of the admin, not of anyone else', function () {
    actingAsAdmin();

    $this->post(route('admin.password.confirm.store'), ['password' => 'not-the-password'])
        ->assertSessionHasErrors('password');

    $this->get(route('admin.access.administrators.index'))
        ->assertRedirect(route('admin.password.confirm'));
});

test('a confirmed admin reaches the protected screen', function () {
    actingAsAdmin();

    $this->post(route('admin.password.confirm.store'), ['password' => 'password'])
        ->assertSessionHasNoErrors();

    $this->get(route('admin.access.administrators.index'))->assertOk();
});

test('signing out drops the admin confirmation', function () {
    actingAsAdmin();

    $this->post(route('admin.password.confirm.store'), ['password' => 'password']);

    expect(session()->has(RequireAdminPasswordConfirmation::SessionKey))->toBeTrue();

    $this->post(route('admin.logout'));

    expect(session()->has(RequireAdminPasswordConfirmation::SessionKey))->toBeFalse();
});
