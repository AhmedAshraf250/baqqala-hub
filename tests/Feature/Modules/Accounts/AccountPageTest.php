<?php

use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\AdminUser;
use App\Foundation\Money\Money;
use App\Modules\Accounts\Contracts\AccountLedgerInterface;
use App\Modules\Accounts\Contracts\Data\ChargeLine;
use App\Modules\Accounts\Contracts\Data\ChargeRequest;
use App\Modules\Accounts\Contracts\Data\PaymentRequest;
use App\Modules\Accounts\Contracts\Data\VoidRequest;
use App\Modules\Accounts\Contracts\Enums\CorrectionReason;
use App\Modules\Accounts\Contracts\Enums\EntryKind;
use App\Modules\Accounts\Contracts\Enums\TransactionType;
use App\Modules\Accounts\Database\Models\AccountTransaction;
use App\Modules\Accounts\Database\Models\CustomerAccount;
use App\Modules\Accounts\Domain\Actions\RecordOpeningBalance;
use App\Modules\Customers\Contracts\Data\CustomerData;
use App\Modules\Customers\Database\Models\Customer;
use App\Modules\Customers\Database\Models\CustomerGroup;
use App\Modules\Customers\Domain\Actions\CreateCustomer;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->admin = actingAsAdmin();
    $this->customer = app(CreateCustomer::class)->handle(['name' => 'محمد أحمد علي', 'phone' => '01001235678', 'address' => 'شارع الجمهورية']);
    $this->ledger = app(AccountLedgerInterface::class);
});

function onAccount(CustomerData $customer, string $amount, array $lines = []): int
{
    return app(AccountLedgerInterface::class)->charge(new ChargeRequest(
        $customer->id,
        AdminUser::query()->firstOrFail()->getKey(),
        (string) Str::uuid(),
        $lines ?: [ChargeLine::free('حاجات', Money::fromDecimalString($amount))],
    ))->first()->entryId;
}

function accountOf(CustomerData $customer): CustomerAccount
{
    return CustomerAccount::query()->where('customer_id', $customer->id)->firstOrFail();
}

test('the account page says who, where it stands, and how it got there', function () {
    onAccount($this->customer, '900.00', [ChargeLine::free('أرز', Money::fromDecimalString('400.00')), ChargeLine::free('زيت', Money::fromDecimalString('500.00'))]);
    $this->ledger->payment(new PaymentRequest($this->customer->id, $this->admin->getKey(), (string) Str::uuid(), Money::fromDecimalString('200.00')));

    $this->get(route('admin.accounts.show', $this->customer->id))
        ->assertOk()
        ->assertSeeText('محمد أحمد علي')
        ->assertSeeText('01001235678')
        ->assertSeeText('شارع الجمهورية')
        ->assertSeeText(__('accounts::module.balance.owes').' 700.00')
        ->assertSeeText(__('accounts::module.statement.title'))
        ->assertSeeText('أرز، زيت')
        ->assertSeeText(__('accounts::module.statement.day_end', ['balance' => __('accounts::module.balance.owes').' 700.00']))
        ->assertSeeText(__('accounts::module.statement.was', ['balance' => __('accounts::module.balance.owes').' 900.00']));
});

test('the account page sits under the customer list in the trail and the sidebar', function () {
    $html = $this->get(route('admin.accounts.show', $this->customer->id))->assertOk()->getContent();

    expect($html)
        ->toContain('<a href="'.route('admin.accounts.index').'">'.e(__('accounts::module.navigation.customers')).'</a>')
        ->toMatch('/<li class="breadcrumb-item active" aria-current="page" dir="auto">\s*'.preg_quote(e('محمد أحمد علي'), '/').'\s*<\/li>/')
        ->toContain('<title>'.e('محمد أحمد علي'));
});

test('a customer with no account, or no such customer, is not found', function () {
    $this->get(route('admin.accounts.show', 9999))->assertNotFound();
});

test('the page is behind the right to see accounts', function () {
    test()->actingAs(AdminUser::factory()->create(), Area::Admin->guard());

    $this->get(route('admin.accounts.show', $this->customer->id))->assertForbidden();
});

test('someone who may only look sees no way to record, adjust, or edit', function () {
    $viewer = AdminUser::factory()->create();
    $viewer->givePermissionTo('accounts.view');
    test()->actingAs($viewer, Area::Admin->guard());
    onAccount($this->customer, '10.00');

    $this->get(route('admin.accounts.show', $this->customer->id))
        ->assertOk()
        ->assertDontSee('id="entry-drawer"', escape: false)
        ->assertDontSeeText(__('accounts::module.account.adjust'))
        ->assertDontSeeText(__('accounts::module.account.edit_customer'))
        ->assertSee('id="entry-details"', escape: false);
});

test('the period and the kind of entry narrow the statement, and are in the address', function () {
    $this->travelTo(now()->subDays(45));
    onAccount($this->customer, '111.00');
    $this->travelBack();
    onAccount($this->customer, '222.00');
    $this->ledger->payment(new PaymentRequest($this->customer->id, $this->admin->getKey(), (string) Str::uuid(), Money::fromDecimalString('33.00')));

    $amounts = fn ($page): array => collect($page->instance()->statement['days'])
        ->flatMap(fn ($day) => $day->entries)
        ->map(fn ($entry) => $entry->amount->toDecimalString())
        ->all();

    $page = Livewire::test('accounts::admin.page.account', ['customer' => $this->customer->id]);

    // The older charge is before the last 30 days: it is the period's opening balance, not a line.
    expect($amounts($page))->toBe(['33.00', '222.00'])
        ->and($page->instance()->periodTotals->opening->toDecimalString())->toBe('111.00')
        ->and($amounts($page->set('period', 'all')))->toBe(['33.00', '222.00', '111.00'])
        ->and($amounts($page->set('kind', 'payments')))->toBe(['33.00']);
});

test('the figures above the statement leave voided entries out', function () {
    $wrong = onAccount($this->customer, '450.00');
    onAccount($this->customer, '100.00');
    $this->ledger->void(new VoidRequest($wrong, $this->admin->getKey(), (string) Str::uuid(), CorrectionReason::Duplicate));

    $page = Livewire::test('accounts::admin.page.account', ['customer' => $this->customer->id]);

    expect($page->instance()->lifetime->taken->toDecimalString())->toBe('100.00')
        ->and($page->instance()->lifetime->voided)->toBe(1);

    $page->assertSeeText(__('accounts::module.statement.voided'))
        ->assertSeeText(__('accounts::module.statement.voids', ['date' => local_time(now())->locale(app()->getLocale())->isoFormat('D MMMM')]));
});

test('a long statement shows a page, and more on request', function () {
    foreach (range(1, 55) as $i) {
        onAccount($this->customer, '1.00');
    }

    $page = Livewire::test('accounts::admin.page.account', ['customer' => $this->customer->id]);

    expect($page->instance()->statement['older'])->toBeTrue();

    $page->call('showOlder');

    expect($page->instance()->statement['older'])->toBeFalse()
        ->and($page->get('limit'))->toBe(100);
});

test('an empty account offers its first entry, an opening balance among them', function () {
    $this->get(route('admin.accounts.show', $this->customer->id))
        ->assertOk()
        ->assertSeeText(__('accounts::module.statement.empty'))
        ->assertSeeText(__('accounts::module.adjust.opening_title'));
});

test('an opening balance comes from the paper book, either way', function (string $direction, string $after) {
    Livewire::test('accounts::admin.account-adjustments')
        ->dispatch('adjust-account', customerId: $this->customer->id, mode: 'opening')
        ->assertDispatched('overlay-show', id: 'account-adjustments')
        ->set('direction', $direction)
        ->set('amount', '٣٠٠')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('account-changed');

    expect(accountOf($this->customer)->outstanding->toDecimalString())->toBe($after)
        ->and(AccountTransaction::query()->sole()->kind)->toBe(EntryKind::OpeningBalance);
})->with([
    'they owe it' => ['debit', '300.00'],
    'it is theirs' => ['credit', '-300.00'],
]);

test('a discount forgives part of what is owed, and never more', function () {
    onAccount($this->customer, '352.50');

    $adjust = Livewire::test('accounts::admin.account-adjustments')
        ->dispatch('adjust-account', customerId: $this->customer->id, mode: 'discount')
        ->set('amount', '400')
        ->call('save')
        ->assertSet('problem', __('accounts::module.adjust.discount_too_much', ['owed' => '352.50']));

    $adjust->set('amount', '2.50')->call('save')->assertSet('problem', null);

    expect(accountOf($this->customer)->outstanding->toDecimalString())->toBe('350.00');
});

test('a credit limit is set, and zero takes it away', function () {
    $adjust = Livewire::test('accounts::admin.account-adjustments')
        ->dispatch('adjust-account', customerId: $this->customer->id, mode: 'limit')
        ->set('amount', '1000')
        ->call('save');

    expect(accountOf($this->customer)->credit_limit->toDecimalString())->toBe('1000.00');

    $adjust->dispatch('adjust-account', customerId: $this->customer->id, mode: 'limit')
        ->assertSet('amount', '1000.00')
        ->set('amount', '0')
        ->call('save');

    expect(accountOf($this->customer)->credit_limit->isZero())->toBeTrue();
});

test('only someone with the adjustments permission may adjust', function () {
    $cashier = AdminUser::factory()->create();
    $cashier->syncRoles('cashier');
    test()->actingAs($cashier, Area::Admin->guard());

    Livewire::test('accounts::admin.account-adjustments')
        ->dispatch('adjust-account', customerId: $this->customer->id, mode: 'opening')
        ->assertForbidden();
});

test('a customer\'s details and groups are changed together, and a taken phone is refused', function () {
    $neighbours = CustomerGroup::factory()->create(['name' => 'جيران']);
    $archived = CustomerGroup::factory()->archived()->create();
    Customer::query()->whereKey($this->customer->id)->firstOrFail()->groups()->attach($archived->id);
    Customer::factory()->create(['phone' => '01119876543']);

    $edit = Livewire::test('accounts::admin.customers.edit-customer')
        ->dispatch('edit-customer', customerId: $this->customer->id)
        ->assertSet('name', 'محمد أحمد علي')
        ->set('phone', '01119876543')
        ->call('save')
        ->assertHasErrors('phone');

    $edit->set('phone', '٠١٠٠ ٠٠٠ ١١١١')
        ->set('name', 'محمد أحمد علي حسن')
        ->set('groupIds', [$neighbours->id])
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('account-changed');

    $customer = Customer::query()->findOrFail($this->customer->id);

    expect($customer->name)->toBe('محمد أحمد علي حسن')
        ->and($customer->phone)->toBe('01000001111')
        // The archived group was never offered, so it stays.
        ->and($customer->groups()->pluck('customer_groups.id')->sort()->values()->all())->toBe(collect([$neighbours->id, $archived->id])->sort()->values()->all());
});

test('only someone who keeps customers may edit one', function () {
    $cashier = AdminUser::factory()->create();
    $cashier->syncRoles('cashier');
    test()->actingAs($cashier, Area::Admin->guard());

    Livewire::test('accounts::admin.customers.edit-customer')
        ->dispatch('edit-customer', customerId: $this->customer->id)
        ->assertForbidden();
});

test('the list leads to the page', function () {
    onAccount($this->customer, '10.00');

    $this->get(route('admin.accounts.index'))
        ->assertSee('href="'.route('admin.accounts.show', $this->customer->id).'"', escape: false);
});

test('how long the balance has been owed, and the last payment, are said under it', function () {
    $this->travelTo(CarbonImmutable::now()->subDays(17));
    onAccount($this->customer, '500.00');
    $this->travelBack();
    $this->ledger->payment(new PaymentRequest($this->customer->id, $this->admin->getKey(), (string) Str::uuid(), Money::fromDecimalString('200.00')));

    $story = Livewire::test('accounts::admin.page.account', ['customer' => $this->customer->id])->instance()->story();

    expect($story)->toContain(__('accounts::module.account.owed_since', ['days' => 17, 'date' => local_time(now()->subDays(17))->locale(app()->getLocale())->isoFormat('D MMMM')]))
        ->toContain('200.00');
});

test('an opening balance shows on the page in words, which way it goes', function () {
    app(RecordOpeningBalance::class)->handle($this->customer->id, $this->admin->getKey(), (string) Str::uuid(), Money::fromDecimalString('300.00'), TransactionType::Debit);

    $this->get(route('admin.accounts.show', $this->customer->id))
        ->assertSeeText(__('accounts::module.account.opening_balances'))
        ->assertSeeText(__('accounts::module.balance.owes').' 300.00');
});
