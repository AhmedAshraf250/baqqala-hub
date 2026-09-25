<?php

use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\AdminUser;
use App\Foundation\Identity\Models\FrontendUser;
use App\Foundation\Identity\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(LazilyRefreshDatabase::class);

test('it creates the first administrator, with a role, on a fresh installation', function () {
    // No seeded roles yet: the command seeds them itself, so one command is
    // all a new installation needs.
    $this->artisan('app:admin:grant', ['email' => 'owner@example.test', '--name' => 'The Owner', '--role' => 'owner'])
        ->expectsQuestion('Choose a password for them', 'a-good-password')
        ->assertSuccessful();

    $admin = AdminUser::query()->where('email', 'owner@example.test')->sole();

    expect($admin->area)->toBe(Area::Admin)
        ->and($admin->hasRole('owner'))->toBeTrue()
        ->and($admin->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check('a-good-password', $admin->password))->toBeTrue();
});

test('it changes the role of an existing administrator', function () {
    $admin = actingAsAdmin();

    $this->artisan('app:admin:grant', ['email' => $admin->email, '--role' => 'cashier'])->assertSuccessful();

    expect($admin->fresh()->getRoleNames()->all())->toBe(['cashier']);
});

test('it refuses to turn a customer\'s login into an administrator', function () {
    // A login opens one area, and a customer's is the key to their tab in the
    // portal. Converting it would lock them out of their own account.
    $customer = FrontendUser::factory()->create();

    $this->artisan('app:admin:grant', ['email' => $customer->email, '--role' => 'owner'])->assertFailed();

    expect(User::query()->find($customer->getKey())->area)->toBe(Area::Frontend);
});

test('it refuses a role the roles file does not define', function () {
    $this->artisan('app:admin:grant', ['email' => 'someone@example.test', '--name' => 'Someone', '--role' => 'emperor'])
        ->assertFailed();

    expect(User::query()->where('email', 'someone@example.test')->exists())->toBeFalse();
});
