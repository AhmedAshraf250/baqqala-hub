<?php

use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('a guest on an admin url is sent to the admin front door', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
});

test('a guest on a customer url is sent to the customer front door', function () {
    $this->get(route('frontend.account.dashboard'))->assertRedirect(route('login'));
});

test('a customer is not even authenticated on the admin guard', function () {
    actingAsCustomer();

    // The admin guard's provider cannot resolve a customer, so on that guard
    // they are a guest — they are bounced to the admin front door rather than
    // reaching a route that then has to refuse them.
    $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    $this->get('/admin')->assertRedirect(route('admin.login'));
});

test('the role check still refuses a session that reached the admin guard', function () {
    // Belt and braces: even if a customer were somehow placed on the admin
    // guard, EnsureUserIsAdmin refuses them.
    $customer = User::factory()->frontend()->create();

    $this->actingAs($customer, Area::Admin->guard());

    $this->get(route('admin.dashboard'))->assertForbidden();
});

test('an admin reaches the admin dashboard', function () {
    actingAsAdmin();

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('app-wrapper', false)
        ->assertSee('sidebar-menu', false);
});

test('an admin is not authenticated on the customer guard', function () {
    actingAsAdmin();

    $this->get(route('frontend.account.dashboard'))->assertRedirect(route('login'));
});

test('a customer reaches the customer dashboard', function () {
    actingAsCustomer();

    $this->get(route('frontend.account.dashboard'))->assertOk();
});

test('the admin shell never loads customer assets', function () {
    // Vite must be on for this to assert anything; the suite disables it.
    $this->withVite();
    actingAsAdmin();

    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee($manifest['resources/js/admin/app.js']['file'], false)
        ->assertDontSee($manifest['resources/css/frontend/app.css']['file'], false)
        ->assertDontSee($manifest['resources/js/frontend/app.js']['file'], false);
});

test('each area has its own sign-in screen', function () {
    $this->get(route('admin.login'))
        ->assertOk()
        ->assertSee('login-box', false)
        ->assertSee(__('foundation.areas.admin'))
        ->assertSee(route('admin.login.store'), false);

    $this->get(route('login'))
        ->assertOk()
        ->assertDontSee('login-box', false)
        ->assertDontSee(route('admin.login.store'), false);
});

test('neither front door offers registration', function () {
    // Identity originates inside the shop; nobody signs themselves up.
    $this->get(route('admin.login'))->assertOk()->assertDontSee('/register', false);
    $this->get(route('login'))->assertOk()->assertDontSee('/register', false);
});

test('administrator credentials do not work at the customer front door', function () {
    $admin = User::factory()->admin()->create();

    $this->post(route('login.store'), [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('customer credentials do not work at the admin front door', function () {
    $customer = User::factory()->frontend()->create();

    $this->post(route('admin.login.store'), [
        'email' => $customer->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('signing in at each front door lands in that area', function () {
    $admin = User::factory()->admin()->create();

    $this->post(route('admin.login.store'), [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertRedirect(route('admin.dashboard'));

    $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));

    $customer = User::factory()->frontend()->create();

    $this->post(route('login.store'), [
        'email' => $customer->email,
        'password' => 'password',
    ])->assertRedirect(route('frontend.account.dashboard'));

    $this->post(route('logout'))->assertRedirect(route('login'));
});

test('settings are area specific and never cross over', function () {
    actingAsAdmin();

    $this->get(route('admin.settings'))
        ->assertOk()
        ->assertSee('app-wrapper', false)
        ->assertDontSee('flux', false);

    $this->get(route('frontend.account.settings.profile'))->assertRedirect(route('login'));
});

test('customer settings stay on the customer shell', function () {
    actingAsCustomer();

    $this->get(route('frontend.account.settings.profile'))
        ->assertOk()
        ->assertDontSee('app-wrapper', false);

    $this->get(route('admin.settings'))->assertRedirect(route('admin.login'));
});
