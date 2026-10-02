<?php

use App\Foundation\Identity\Models\User;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('frontend.account.dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrorsIn('email');

    $this->assertGuest();
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    // Signing out returns the visitor to the front door they came through.
    $response->assertRedirect(route('login'));

    $this->assertGuest();
});

test('each front door counts its own failed sign-ins', function () {
    // The key was address and IP alone, so locking someone out of the admin
    // area locked them out of their portal too — and the other way round.
    $attempt = ['email' => 'someone@example.com', 'password' => 'wrong-password'];

    foreach (range(1, 5) as $ignored) {
        $this->post(route('admin.login.store'), $attempt);
    }

    $this->post(route('admin.login.store'), $attempt)->assertTooManyRequests();
    $this->post(route('login.store'), $attempt)->assertRedirect()->assertSessionHasErrors('email');
});

test('an address is stored lowercased, and signs in however it is typed', function () {
    $user = User::factory()->create(['email' => ' Owner@Shop.COM ']);

    expect($user->fresh()->email)->toBe('owner@shop.com');

    $this->post(route('login.store'), ['email' => 'OWNER@shop.com', 'password' => 'password'])
        ->assertSessionHasNoErrors();

    $this->assertAuthenticated();
});
