<?php

use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\AdminUser;
use App\Foundation\Identity\Models\FrontendUser;
use App\Foundation\Identity\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

/**
 * `AdminUser` and `FrontendUser` are subclasses of `User` sharing one table.
 * Several Laravel conventions are derived from the class name, and each one
 * left alone silently points a subclass at a column that does not exist.
 * These tests pin every such convention.
 *
 * @return list<class-string<User>>
 */
function userModels(): array
{
    return [User::class, AdminUser::class, FrontendUser::class];
}

test('every user model resolves the same table', function (string $model) {
    expect((new $model)->getTable())->toBe('users');
})->with(userModels());

test('every user model uses the same foreign key', function (string $model) {
    // Otherwise a relation on FrontendUser looks for `customer_user_id`.
    expect((new $model)->getForeignKey())->toBe('user_id');
})->with(userModels());

test('every user model shares one morph class', function (string $model) {
    // Otherwise a polymorphic row written as FrontendUser is invisible to User.
    expect((new $model)->getMorphClass())->toBe(User::class);
})->with(userModels());

test('every user model uses the same route key', function (string $model) {
    expect((new $model)->getRouteKeyName())->toBe((new User)->getRouteKeyName());
})->with(userModels());

test('passkeys resolve through every user model', function (string $model) {
    $user = $model::factory()->create();

    // The bug this covers: the query asked for `passkeys.customer_user_id`.
    expect($user->passkeys()->getForeignKeyName())->toBe('user_id')
        ->and($user->passkeys()->count())->toBe(0);
})->with(userModels());

test('a relation declared on the base model resolves the same key from every subclass', function (string $model) {
    // The general form of the passkeys bug: any `hasOne`/`hasMany` onto a
    // table with a `user_id` column must find it whichever class loaded the row.
    $user = $model::factory()->create();

    expect($user->hasMany(User::class, $user->getForeignKey())->getForeignKeyName())->toBe('user_id');
})->with(userModels());

test('the area a login opens cannot be mass-assigned', function (string $model) {
    expect((new $model)->isFillable('area'))->toBeFalse();
})->with(userModels());

test('a form that forgets to strip the area cannot promote anyone', function () {
    // The shape of the mistake: input passed straight to `create()`.
    $input = ['name' => 'Someone', 'password' => 'a-good-password', 'area' => Area::Admin->value];

    $customer = FrontendUser::create([...$input, 'email' => 'customer@example.test']);
    $unscoped = User::create([...$input, 'email' => 'unscoped@example.test']);

    expect($customer->fresh()->area)->toBe(Area::Frontend)
        // Without an area model, the column's own default applies — the least
        // privileged one.
        ->and($unscoped->fresh()->area)->toBe(Area::Frontend);
});

test('each area model creates logins for its own area only', function () {
    expect((new AdminUser)->area)->toBe(Area::Admin)
        ->and((new FrontendUser)->area)->toBe(Area::Frontend);
});
