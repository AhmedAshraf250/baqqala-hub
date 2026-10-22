<?php

use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\AdminUser;
use App\Foundation\Money\Money;
use App\Modules\Accounts\Contracts\AccountLedgerInterface;
use App\Modules\Accounts\Contracts\Data\ChargeLine;
use App\Modules\Accounts\Contracts\Data\ChargeRequest;
use App\Modules\Accounts\Contracts\Data\PaymentRequest;
use App\Modules\Accounts\Contracts\Enums\OverpaymentChoice;
use App\Modules\Accounts\Database\Models\CustomerAccount;
use App\Modules\Customers\Contracts\Data\CustomerData;
use App\Modules\Customers\Database\Models\Customer;
use App\Modules\Customers\Database\Models\CustomerGroup;
use App\Modules\Customers\Domain\Actions\CreateCustomer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->admin = actingAsAdmin();
});

/**
 * A customer added the way the counter adds one — so their account opens —
 * owing what they took, less what they paid.
 */
function customerOnBooks(string $name, ?string $phone = null, ?string $took = null, ?string $paid = null): CustomerData
{
    $customer = app(CreateCustomer::class)->handle(['name' => $name, 'phone' => $phone]);
    $ledger = app(AccountLedgerInterface::class);
    $by = AdminUser::query()->firstOrFail()->getKey();

    if ($took !== null) {
        $ledger->charge(new ChargeRequest($customer->id, $by, (string) Str::uuid(), [ChargeLine::free('حاجات', Money::fromDecimalString($took))]));
    }

    if ($paid !== null) {
        $ledger->payment(new PaymentRequest($customer->id, $by, (string) Str::uuid(), Money::fromDecimalString($paid), overpayment: OverpaymentChoice::KeepAsCredit));
    }

    return $customer;
}

/**
 * Sign in as an administrator holding one of the seeded roles.
 */
function signedInAs(string $role): AdminUser
{
    $user = AdminUser::factory()->create();
    $user->syncRoles($role);
    test()->actingAs($user, Area::Admin->guard());

    return $user;
}

function customerList(): Testable
{
    return Livewire::test('accounts::admin.page.customers');
}

test('every customer is listed with where their account stands, in words', function () {
    customerOnBooks('أحمد سعيد', '01001234567', took: '350.00');
    customerOnBooks('منى عادل', '01119876543', took: '100.00', paid: '150.00');
    customerOnBooks('سعيد البقال');

    $this->get(route('admin.accounts.index'))
        ->assertOk()
        ->assertSeeText('أحمد سعيد')
        ->assertSeeText(__('accounts::module.balance.owes'))
        ->assertSeeText('350.00')
        ->assertSeeText(__('accounts::module.balance.in_credit'))
        ->assertSeeText('50.00')
        ->assertSeeText(__('accounts::module.balance.settled'))
        ->assertSeeText('01001234567');
});

test('the totals line sums whatever the list shows', function () {
    customerOnBooks('Owes A', took: '300.00');
    customerOnBooks('Owes B', took: '50.50');
    customerOnBooks('In credit', took: '10.00', paid: '30.00');
    customerOnBooks('Settled');

    $totals = customerList()->instance()->totals;

    expect($totals->accounts)->toBe(4)
        ->and($totals->owing)->toBe(2)
        ->and($totals->owed->toDecimalString())->toBe('350.50')
        ->and($totals->inCredit)->toBe(1)
        ->and($totals->heldForThem->toDecimalString())->toBe('20.00')
        ->and($totals->settled)->toBe(1);
});

test('the search finds a customer by any spelling of the name or a part of the phone', function (string $typed) {
    customerOnBooks('أحمد سعيد', '01001234567');
    customerOnBooks('منى عادل', '01119876543');

    customerList()
        ->set('q', $typed)
        ->assertSeeText('أحمد سعيد')
        ->assertDontSeeText('منى عادل');
})->with([
    'without the hamza' => ['احمد'],
    'the last digits' => ['4567'],
    'Arabic-Indic digits' => ['٤٥٦٧'],
]);

test('a group chip narrows the list to the people in it, and pressing it again lets go', function () {
    $wholesale = CustomerGroup::factory()->create(['name' => 'جملة']);
    $trader = customerOnBooks('Trader');
    customerOnBooks('Neighbour');
    $wholesale->members()->attach($trader->id);

    customerList()
        ->call('selectGroup', $wholesale->id)
        ->assertSet('group', $wholesale->id)
        ->assertSeeText('Trader')
        ->assertDontSeeText('Neighbour')
        ->call('selectGroup', $wholesale->id)
        ->assertSet('group', null)
        ->assertSeeText('Neighbour');
});

test('the balance filter keeps who owes, who is settled, who is in credit, and who is past their limit', function (string $filter, array $kept) {
    customerOnBooks('Owes', took: '100.00');
    customerOnBooks('Settled');
    customerOnBooks('In credit', paid: '20.00');
    $overLimit = customerOnBooks('Past the limit', took: '500.00');
    CustomerAccount::query()->where('customer_id', $overLimit->id)->update(['credit_limit' => 30000]);

    $names = customerList()->set('balance', $filter)->instance()->rows->getCollection()->map(fn ($row) => $row->customer->name)->all();

    expect($names)->toEqualCanonicalizing($kept);
})->with([
    // Past the limit is owing too.
    'owes' => ['owes', ['Owes', 'Past the limit']],
    'settled' => ['settled', ['Settled']],
    'in credit' => ['credit', ['In credit']],
    'over the limit' => ['over_limit', ['Past the limit']],
]);

test('a debt with no payment for a while is late, and one with no entry for longer is dormant', function () {
    $this->travelTo(now()->subDays(100));
    customerOnBooks('Quiet for months', took: '80.00');
    $this->travelBack();

    $this->travelTo(now()->subDays(40));
    customerOnBooks('Paid once, long ago', took: '200.00', paid: '50.00');
    $this->travelBack();

    customerOnBooks('Just took', took: '20.00');

    $list = customerList();

    expect($list->set('activity', 'late')->instance()->rows->getCollection()->map(fn ($row) => $row->customer->name)->all())
        ->toEqualCanonicalizing(['Quiet for months', 'Paid once, long ago'])
        ->and($list->set('activity', 'dormant')->instance()->rows->getCollection()->map(fn ($row) => $row->customer->name)->all())
        ->toBe(['Quiet for months']);

    $rows = $list->set('activity', '')->instance()->rows->getCollection()->keyBy(fn ($row) => $row->customer->name);

    expect($rows['Quiet for months']->dormantDays)->toBeGreaterThanOrEqual(90)
        ->and($rows['Quiet for months']->hasPaid)->toBeFalse()
        ->and($rows['Paid once, long ago']->lateDays)->toBeGreaterThanOrEqual(30)
        ->and($rows['Paid once, long ago']->hasPaid)->toBeTrue()
        ->and($rows['Just took']->hasWarnings())->toBeFalse();

    customerList()->assertSeeText(__('accounts::module.list.alert_late_paid', ['days' => $rows['Paid once, long ago']->lateDays]));
});

test('the latest entry comes first, and the largest balance on request', function () {
    $this->travelTo(now()->subHour());
    customerOnBooks('Earlier, more', took: '900.00');
    $this->travelBack();
    customerOnBooks('Later, less', took: '10.00');
    customerOnBooks('Nothing yet');

    $names = fn ($list) => $list->instance()->rows->getCollection()->map(fn ($row) => $row->customer->name)->all();

    expect($names(customerList()))->toBe(['Later, less', 'Earlier, more', 'Nothing yet'])
        ->and($names(customerList()->set('sort', 'largest')))->toBe(['Earlier, more', 'Later, less', 'Nothing yet']);
});

test('every filter is in the page address, and an unknown one is ignored', function () {
    customerOnBooks('Owes', took: '100.00');

    Livewire::withQueryParams(['balance' => 'owes', 'sort' => 'no-such-order'])
        ->test('accounts::admin.page.customers')
        ->assertSet('balance', 'owes')
        ->assertSeeText('Owes');
});

test('a balance past its limit says by how much, and what the limit is', function () {
    $over = customerOnBooks('Past the limit', took: '150.00');
    customerOnBooks('Owes more, no limit', took: '900.00');
    CustomerAccount::query()->where('customer_id', $over->id)->update(['credit_limit' => 10000]);

    app()->setLocale('ar');

    customerList()
        ->assertSeeText(__('accounts::module.list.alert_over_limit', ['over' => '50.00', 'limit' => '100.00']))
        ->assertSeeText('تخطّى حده بـ 50.00 (الحد 100.00)');

    // Owing more is not past a limit when there is none: one row warns,
    // drawn twice — in its own column, and under the name on a narrow screen.
    expect(substr_count(customerList()->html(), 'bi-exclamation-triangle'))->toBe(2);
});

test('a filter in force is marked as one, and one press lets go of them all', function () {
    customerOnBooks('Owes', took: '100.00');

    customerList()
        ->assertDontSeeHtml('is-filtering')
        ->assertDontSeeText(__('accounts::module.list.clear_filters'))
        ->set('balance', 'owes')
        ->assertSeeHtml('is-filtering')
        ->assertSeeText(__('accounts::module.list.clear_filters'))
        ->call('clearFilters')
        ->assertSet('balance', '')
        ->assertDontSeeHtml('is-filtering');
});

test('an empty book, a search that finds nobody, and filters that keep nobody each say so', function () {
    customerList()
        ->assertSeeText(__('accounts::module.list.empty_title'));

    customerOnBooks('Someone');

    customerList()
        ->set('q', 'محمود الجزار')
        ->assertSeeText(__('accounts::module.list.no_match_name', ['term' => 'محمود الجزار']))
        ->assertSeeText(__('accounts::module.list.add_named', ['term' => 'محمود الجزار']))
        ->set('q', '')
        ->set('balance', 'owes')
        ->assertSeeText(__('accounts::module.list.no_match_filters'))
        ->call('clearFilters')
        ->assertSet('balance', '')
        ->assertSeeText('Someone');
});

test('the page reads the same handful of queries for five customers or thirty', function () {
    $queries = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('admin.accounts.index'))->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    $group = CustomerGroup::factory()->create();

    // The first page with a row on it also fills what later requests read
    // from memory: the roles, the settings behind the local clock.
    $group->members()->attach(customerOnBooks('Customer 1', took: '10.00')->id);
    $queries();

    foreach (range(2, 5) as $i) {
        $group->members()->attach(customerOnBooks("Customer {$i}", took: '10.00')->id);
    }

    $few = $queries();

    foreach (range(6, 30) as $i) {
        $group->members()->attach(customerOnBooks("Customer {$i}", took: '10.00')->id);
    }

    expect($queries())->toBe($few);
});

test('a cashier records and adds customers, and neither keeps groups nor picks rows for them', function () {
    signedInAs('cashier');
    customerOnBooks('Someone', took: '10.00');

    $this->get(route('admin.accounts.index'))
        ->assertOk()
        ->assertSeeText(__('accounts::module.list.new_customer'))
        ->assertSeeText(__('accounts::module.list.charge'))
        ->assertDontSeeText(__('accounts::module.list.manage_groups'))
        ->assertDontSee('wire:model.live="selected"', escape: false);
});

test('someone who may only look sees no way to record', function () {
    $viewer = AdminUser::factory()->create();
    $viewer->givePermissionTo('accounts.view');
    test()->actingAs($viewer, Area::Admin->guard());
    customerOnBooks('Someone', took: '10.00');

    $this->get(route('admin.accounts.index'))
        ->assertOk()
        ->assertSeeText('Someone')
        ->assertDontSee(__('accounts::module.list.charge_for', ['name' => 'Someone']))
        ->assertDontSeeText(__('accounts::module.list.new_customer'));
});

test('picked customers join a group at once', function () {
    $group = CustomerGroup::factory()->create(['name' => 'قريبين']);
    $first = customerOnBooks('First');
    $second = customerOnBooks('Second');

    customerList()
        ->set('selected', [$first->id, $second->id])
        ->set('bulkGroup', $group->id)
        ->call('addSelectedToGroup')
        ->assertSet('selected', [])
        ->assertSeeText(trans_choice('accounts::module.list.added_to_group', 2, ['count' => 2, 'group' => 'قريبين']));

    expect($group->members()->count())->toBe(2);
});

test('only someone who keeps the groups may put customers in one', function () {
    signedInAs('cashier');
    $group = CustomerGroup::factory()->create();

    customerList()
        ->set('selected', [customerOnBooks('Someone')->id])
        ->set('bulkGroup', $group->id)
        ->call('addSelectedToGroup')
        ->assertForbidden();
});

test('the counter adds a customer, with the phone as its digits, and the list shows them', function () {
    Livewire::test('accounts::admin.customers.new-customer')
        ->set('name', 'أم كريم')
        ->set('phone', '٠١٠٠ ١٢٣ ٤٥٦٧')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('overlay-hide', id: 'new-customer')
        ->assertDispatched('customer-added', term: '01001234567');

    expect(Customer::query()->where('name', 'أم كريم')->value('phone'))->toBe('01001234567')
        ->and(CustomerAccount::query()->count())->toBe(1);

    customerList()
        ->dispatch('customer-added', term: '01001234567', notice: 'added')
        ->assertSet('q', '01001234567')
        ->assertSeeText('أم كريم');
});

test('a number someone already has is refused, with a way to show them instead', function () {
    customerOnBooks('أم كريم', '01001234567');

    Livewire::test('accounts::admin.customers.new-customer')
        ->set('name', 'Someone else')
        ->set('phone', '0100-123-4567')
        ->call('save')
        ->assertHasErrors('phone')
        ->assertSet('holderName', 'أم كريم')
        ->assertSeeText(__('accounts::module.customer_form.phone_taken_open', ['name' => 'أم كريم']))
        ->call('showHolder')
        ->assertDispatched('customer-added', term: '01001234567');

    expect(Customer::query()->count())->toBe(1);
});

test('a name is required, and what was searched for fills the form', function () {
    Livewire::test('accounts::admin.customers.new-customer')
        ->call('save')
        ->assertHasErrors(['name' => 'required'])
        ->dispatch('new-customer-prefill', term: 'محمود الجزار')
        ->assertSet('name', 'محمود الجزار')
        ->assertSet('phone', '')
        ->assertDispatched('overlay-show', id: 'new-customer')
        ->dispatch('new-customer-prefill', term: '٠١٠٠ ١٢٣')
        ->assertSet('name', '')
        ->assertSet('phone', '٠١٠٠ ١٢٣');
});

test('only someone who may add customers can add one', function () {
    $viewer = AdminUser::factory()->create();
    $viewer->givePermissionTo('accounts.view');
    test()->actingAs($viewer, Area::Admin->guard());

    Livewire::test('accounts::admin.customers.new-customer')
        ->set('name', 'Sneaked in')
        ->call('save')
        ->assertForbidden();

    expect(Customer::query()->count())->toBe(0);
});

test('a group is made, edited, archived, restored, and deleted only while empty', function () {
    $groups = Livewire::test('accounts::admin.customers.groups')
        ->set('name', 'جملة')
        ->set('color', 'teal')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('groups-changed');

    $group = CustomerGroup::query()->sole();
    expect($group->name)->toBe('جملة')
        ->and($group->color->value)->toBe('teal')
        ->and($group->created_by)->toBe($this->admin->getKey());

    $groups->call('edit', $group->id)
        ->assertSet('name', 'جملة')
        ->set('name', 'تجار الجملة')
        ->call('save');

    expect($group->fresh()->name)->toBe('تجار الجملة');

    $group->members()->attach(customerOnBooks('Trader')->id);

    // Someone is in it: archived, never deleted.
    $groups->call('delete', $group->id);
    expect(CustomerGroup::query()->whereKey($group->id)->exists())->toBeTrue();

    $groups->call('archive', $group->id);
    expect($group->fresh()->archived_at)->not->toBeNull()
        ->and($group->members()->count())->toBe(1);

    $groups->call('restore', $group->id);
    expect($group->fresh()->archived_at)->toBeNull();

    $empty = CustomerGroup::factory()->create();
    $groups->call('delete', $empty->id);
    expect(CustomerGroup::query()->whereKey($empty->id)->exists())->toBeFalse();
});

test('a group needs a name and one of the eight colours', function () {
    Livewire::test('accounts::admin.customers.groups')
        ->set('name', '')
        ->set('color', 'chartreuse')
        ->call('save')
        ->assertHasErrors(['name', 'color']);
});

test('only someone who keeps the groups may change them', function () {
    signedInAs('cashier');

    Livewire::test('accounts::admin.customers.groups')
        ->set('name', 'Mine')
        ->call('save')
        ->assertForbidden();
});
