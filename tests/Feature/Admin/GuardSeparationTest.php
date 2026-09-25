<?php

use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\AdminUser;
use App\Foundation\Identity\Models\FrontendUser;
use App\Foundation\Identity\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(LazilyRefreshDatabase::class);

test('the two areas authenticate on different guards', function () {
    expect(Area::Admin->guard())->toBe('admin')
        ->and(Area::Frontend->guard())->toBe('web')
        ->and(config('auth.guards.admin.provider'))->toBe('admins')
        ->and(config('auth.guards.web.provider'))->toBe('frontend_users');
});

test('the admin model cannot load a customer row, even by key', function () {
    $customer = User::factory()->frontend()->create();

    expect(AdminUser::query()->find($customer->getKey()))->toBeNull()
        ->and(AdminUser::query()->where('email', $customer->email)->first())->toBeNull();
});

test('the customer model cannot load an admin row, even by key', function () {
    $admin = User::factory()->admin()->create();

    expect(FrontendUser::query()->find($admin->getKey()))->toBeNull()
        ->and(FrontendUser::query()->where('email', $admin->email)->first())->toBeNull();
});

test('a customer session does not resolve on the admin guard', function () {
    $customer = FrontendUser::factory()->create();

    $this->actingAs($customer, Area::Frontend->guard());

    // The session holds a real, signed-in customer...
    expect(Auth::guard(Area::Frontend->guard())->check())->toBeTrue()
        // ...and the admin guard still sees nobody.
        ->and(Auth::guard(Area::Admin->guard())->check())->toBeFalse();
});

test('an admin session does not resolve on the customer guard', function () {
    $admin = AdminUser::factory()->create();

    $this->actingAs($admin, Area::Admin->guard());

    expect(Auth::guard(Area::Admin->guard())->check())->toBeTrue()
        ->and(Auth::guard(Area::Frontend->guard())->check())->toBeFalse();
});

test('signing out of one area leaves the other untouched', function () {
    $this->actingAs(AdminUser::factory()->create(), Area::Admin->guard());
    $this->actingAs(FrontendUser::factory()->create(), Area::Frontend->guard());

    Auth::guard(Area::Admin->guard())->logout();

    expect(Auth::guard(Area::Admin->guard())->check())->toBeFalse()
        ->and(Auth::guard(Area::Frontend->guard())->check())->toBeTrue();
});

test('administrator credentials are rejected at the customer front door', function () {
    $admin = User::factory()->admin()->create();

    $this->post(route('login.store'), [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertSessionHasErrors();

    expect(Auth::guard(Area::Frontend->guard())->check())->toBeFalse()
        ->and(Auth::guard(Area::Admin->guard())->check())->toBeFalse();
});

test('customer credentials are rejected at the admin front door', function () {
    $customer = User::factory()->frontend()->create();

    $this->post(route('admin.login.store'), [
        'email' => $customer->email,
        'password' => 'password',
    ])->assertSessionHasErrors();

    expect(Auth::guard(Area::Admin->guard())->check())->toBeFalse()
        ->and(Auth::guard(Area::Frontend->guard())->check())->toBeFalse();
});

test('a administrators member signs in on the admin guard only', function () {
    $admin = AdminUser::factory()->create();

    $this->post(route('admin.login.store'), [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertRedirect(route('admin.dashboard'));

    expect(Auth::guard(Area::Admin->guard())->id())->toBe($admin->getKey())
        ->and(Auth::guard(Area::Frontend->guard())->check())->toBeFalse();
});

test('a administrators member with two factor enabled is not signed in until they pass it', function () {
    $admin = AdminUser::factory()->withTwoFactor()->create();

    $this->post(route('admin.login.store'), [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertRedirect(route('admin.two-factor.login'));

    expect(Auth::guard(Area::Admin->guard())->check())->toBeFalse();

    $this->assertEquals($admin->getKey(), session('login.id'));
});

test('a spent recovery code cannot be reused', function () {
    $admin = AdminUser::factory()->withTwoFactor()->create();

    $this->post(route('admin.login.store'), [
        'email' => $admin->email,
        'password' => 'password',
    ]);

    $this->post(route('admin.two-factor.login.store'), ['recovery_code' => 'recovery-code-1'])
        ->assertRedirect(route('admin.dashboard'));

    expect(Auth::guard(Area::Admin->guard())->id())->toBe($admin->getKey());

    Auth::guard(Area::Admin->guard())->logout();
    session()->flush();

    $this->post(route('admin.login.store'), [
        'email' => $admin->email,
        'password' => 'password',
    ]);

    // The code was burned on first use; a second attempt must fail.
    $this->post(route('admin.two-factor.login.store'), ['recovery_code' => 'recovery-code-1'])
        ->assertSessionHasErrors('recovery_code');

    expect(Auth::guard(Area::Admin->guard())->check())->toBeFalse();
});

test('signing out of the admin area returns to the admin front door', function () {
    $this->actingAs(AdminUser::factory()->create(), Area::Admin->guard());

    $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));

    expect(Auth::guard(Area::Admin->guard())->check())->toBeFalse();
});

test('screens never read logins through the unscoped model', function () {
    // `AdminUser` and `FrontendUser` each add their area to every query; the
    // base `User` adds nothing. A screen that asked `User` for a login could
    // be handed the other area's — so screen code, in both areas, only ever
    // reads logins through its own area's model. Console commands are left
    // out on purpose: `app:admin:grant` looks a login up across both areas
    // to refuse a customer's.
    $screens = [];

    foreach ([app_path('Admin/Http'), app_path('Frontend/Http'), resource_path('views/admin'), resource_path('views/frontend')] as $directory) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                $screens[] = $file->getPathname();
            }
        }
    }

    expect($screens)->not->toBeEmpty();

    $offenders = array_filter(
        $screens,
        fn (string $file) => preg_match('/(?<![A-Za-z])User::|DB::table\([\'"]users/', (string) file_get_contents($file)) === 1,
    );

    expect(array_values($offenders))->toBe([]);
});
