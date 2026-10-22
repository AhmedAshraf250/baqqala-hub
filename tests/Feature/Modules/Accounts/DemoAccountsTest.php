<?php

use App\Foundation\Identity\Models\AdminUser;
use App\Modules\Accounts\Contracts\Enums\EntryKind;
use App\Modules\Accounts\Database\Models\AccountTransaction;
use App\Modules\Accounts\Database\Models\AccountTransactionItem;
use App\Modules\Accounts\Domain\Services\ReconcileAccountTotals;
use App\Modules\Customers\Database\Models\Customer;
use App\Modules\Customers\Database\Models\CustomerGroup;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

test('the demo is a shop to try the screens on, posted through the real ledger', function () {
    $owner = actingAsAdmin();
    $this->artisan('app:catalog:install-starter')->assertSuccessful();

    $this->artisan('app:accounts:demo')
        ->expectsOutputToContain($owner->email)
        ->assertSuccessful();

    expect(Customer::query()->count())->toBe(40)
        // Three of the same name, for the screens to tell apart.
        ->and(Customer::query()->where('name', 'like', 'محمد أحمد %')->count())->toBe(3)
        ->and(CustomerGroup::query()->count())->toBe(4)
        // Every balance and summary is what the ledger says it is.
        ->and(app(ReconcileAccountTotals::class)->findings())->toBe([])
        ->and(AccountTransactionItem::query()->whereNotNull('product_id')->exists())->toBeTrue()
        ->and(AccountTransaction::query()->where('kind', EntryKind::Reversal)->exists())->toBeTrue()
        ->and(AccountTransaction::query()->whereNotNull('replaces_id')->exists())->toBeTrue()
        ->and(AccountTransaction::query()->where('created_at', '>', now())->exists())->toBeFalse();

    // Every warning the list gives has someone to give it to.
    $kept = fn (string $filter, string $value): int => Livewire::test('accounts::admin.page.customers')->set($filter, $value)->instance()->totals->accounts;

    expect($kept('activity', 'late'))->toBeGreaterThan(0)
        ->and($kept('activity', 'dormant'))->toBeGreaterThan(0)
        ->and($kept('balance', 'over_limit'))->toBeGreaterThan(0)
        ->and($kept('balance', 'credit'))->toBeGreaterThan(0)
        ->and($kept('balance', 'settled'))->toBeGreaterThan(0);
});

test('a second run adds nothing', function () {
    actingAsAdmin();

    $this->artisan('app:accounts:demo')->assertSuccessful();
    $entries = AccountTransaction::query()->count();

    $this->artisan('app:accounts:demo')
        ->expectsOutputToContain('already on the books')
        ->assertSuccessful();

    expect(Customer::query()->count())->toBe(40)
        ->and(AccountTransaction::query()->count())->toBe($entries);
});

test('with no catalog it records amounts alone', function () {
    actingAsAdmin();

    $this->artisan('app:accounts:demo')->assertSuccessful();

    expect(AccountTransactionItem::query()->count())->toBeGreaterThan(0)
        ->and(AccountTransactionItem::query()->whereNotNull('product_id')->exists())->toBeFalse();
});

test('it never runs in production', function () {
    AdminUser::factory()->create();
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('app:accounts:demo')->assertFailed();

    expect(Customer::query()->count())->toBe(0);
});

test('it needs an administrator to have recorded the entries', function () {
    $this->artisan('app:accounts:demo')
        ->expectsOutputToContain('app:admin:grant')
        ->assertFailed();

    expect(Customer::query()->count())->toBe(0);
});
