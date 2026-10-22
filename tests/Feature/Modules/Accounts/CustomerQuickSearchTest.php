<?php

use App\Admin\Search\SearchResult;
use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\AdminUser;
use App\Foundation\Money\Money;
use App\Modules\Accounts\Contracts\AccountLedgerInterface;
use App\Modules\Accounts\Contracts\Data\ChargeLine;
use App\Modules\Accounts\Contracts\Data\ChargeRequest;
use App\Modules\Customers\Database\Models\CustomerGroup;
use App\Modules\Customers\Domain\Actions\CreateCustomer;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->owner = actingAsAdmin();
    $this->travelTo(CarbonImmutable::parse('2026-10-02 12:00', 'Africa/Cairo'));

    $this->mohamed = app(CreateCustomer::class)->handle(['name' => 'محمد أحمد علي', 'phone' => '01001235678']);
    $this->other = app(CreateCustomer::class)->handle(['name' => 'محمد أحمدي']);

    $neighbours = CustomerGroup::factory()->create(['name' => 'جيران']);
    $neighbours->members()->attach($this->mohamed->id);
});

/**
 * The customers group of the quick search, as it reads.
 *
 * @return list<SearchResult>
 */
function customersFound(string $typed): array
{
    $palette = Livewire::test('admin::layout.search-palette')->call('open')->set('q', $typed);

    return collect($palette->instance()->groups)->firstWhere('label', __('accounts::module.search.customers'))['results'] ?? [];
}

function chargeFor(int $customerId, string $amount): void
{
    app(AccountLedgerInterface::class)->charge(new ChargeRequest($customerId, test()->owner->getKey(), (string) Str::uuid(), [ChargeLine::free('طلبات', Money::fromDecimalString($amount))]));
}

test('a customer is found with the end of their phone, their groups, and where their account stands', function () {
    chargeFor($this->mohamed->id, '1250.00');

    $found = customersFound('محمد احمد علي');

    expect($found)->toHaveCount(1)
        ->and($found[0]->title)->toBe('محمد أحمد علي')
        ->and($found[0]->subtitle)->toBe('…5678 · جيران')
        ->and($found[0]->badge)->toBe(__('accounts::module.balance.owes').' 1,250.00')
        ->and($found[0]->tone)->toBe('debit')
        ->and($found[0]->url)->toBe(route('admin.accounts.show', $this->mohamed->id));
});

test('«+» and «−» on a customer open their account with the drawer ready', function () {
    $found = customersFound('5678');

    expect(array_map(fn ($action) => [$action->key, $action->url], $found[0]->actions))->toBe([
        ['+', route('admin.accounts.show', ['customer' => $this->mohamed->id, 'entry' => 'charge'])],
        ['-', route('admin.accounts.show', ['customer' => $this->mohamed->id, 'entry' => 'payment'])],
    ]);

    Livewire::withQueryParams(['entry' => 'charge'])
        ->test('accounts::admin.page.account', ['customer' => $this->mohamed->id])
        ->assertDispatched('open-entry', customerId: $this->mohamed->id, kind: 'charge');

    Livewire::withQueryParams(['entry' => 'anything'])
        ->test('accounts::admin.page.account', ['customer' => $this->mohamed->id])
        ->assertNotDispatched('open-entry');
});

test('before anything is typed, whoever was served today, the latest first', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-01 20:00', 'Africa/Cairo'));
    $yesterday = app(CreateCustomer::class)->handle(['name' => 'زبون أمس']);
    chargeFor($yesterday->id, '10.00');

    $this->travelTo(CarbonImmutable::parse('2026-10-02 09:00', 'Africa/Cairo'));
    chargeFor($this->other->id, '20.00');
    $this->travelTo(CarbonImmutable::parse('2026-10-02 11:00', 'Africa/Cairo'));
    chargeFor($this->mohamed->id, '30.00');

    expect(array_map(fn ($result) => $result->title, customersFound('')))->toBe(['محمد أحمد علي', 'محمد أحمدي']);
});

test('nobody by that name: the offer to add them', function () {
    $found = customersFound('محمود الجزار');

    expect($found)->toHaveCount(1)
        ->and($found[0]->title)->toBe(__('accounts::module.search.add', ['name' => 'محمود الجزار']))
        ->and($found[0]->url)->toBe(route('admin.accounts.index', ['q' => 'محمود الجزار']));
});

test('whoever may not see accounts finds no customers, and whoever may not record gets no keys', function () {
    $nobody = AdminUser::factory()->create();
    $this->actingAs($nobody, Area::Admin->guard());

    expect(customersFound('محمد'))->toBe([]);

    $cashier = AdminUser::factory()->create();
    $cashier->syncRoles('cashier');
    $this->actingAs($cashier, Area::Admin->guard());
    // Another person is another request: the search's sources are read again.
    app()->forgetScopedInstances();

    expect(customersFound('محمد'))->not->toBe([])
        ->and(customersFound('محمد')[0]->actions)->toHaveCount(2);
});
