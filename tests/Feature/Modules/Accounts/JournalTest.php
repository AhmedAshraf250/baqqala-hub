<?php

use App\Foundation\Identity\Models\AdminUser;
use App\Foundation\Money\Money;
use App\Foundation\Settings\SettingStore;
use App\Foundation\Time\LocalClock;
use App\Modules\Accounts\Contracts\AccountLedgerInterface;
use App\Modules\Accounts\Contracts\Data\ChargeLine;
use App\Modules\Accounts\Contracts\Data\ChargeRequest;
use App\Modules\Accounts\Contracts\Data\PaymentRequest;
use App\Modules\Accounts\Contracts\Data\VoidRequest;
use App\Modules\Accounts\Contracts\Enums\CorrectionReason;
use App\Modules\Accounts\Contracts\Enums\OverpaymentChoice;
use App\Modules\Customers\Domain\Actions\CreateCustomer;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

/**
 * Two people at the counter on 2 October, and one entry the day before:
 * Sara takes 350 on account for Mohamed and collects 200 from Nadia; Omar
 * charges 90, voids it at once, and collects 50.
 */
beforeEach(function () {
    $this->owner = actingAsAdmin();
    $this->sara = AdminUser::factory()->create(['name' => 'سارة']);
    $this->omar = AdminUser::factory()->create(['name' => 'عمر']);

    $ledger = app(AccountLedgerInterface::class);
    $mohamed = app(CreateCustomer::class)->handle(['name' => 'محمد أحمد']);
    $nadia = app(CreateCustomer::class)->handle(['name' => 'نادية سمير']);
    $at = fn (string $moment) => $this->travelTo(CarbonImmutable::parse("2026-10-{$moment}", 'Africa/Cairo'));
    $charge = fn ($customer, AdminUser $by, string $amount) => $ledger->charge(new ChargeRequest($customer->id, $by->getKey(), (string) Str::uuid(), [ChargeLine::free('طلبات', Money::fromDecimalString($amount))]));
    $pay = fn ($customer, AdminUser $by, string $amount) => $ledger->payment(new PaymentRequest($customer->id, $by->getKey(), (string) Str::uuid(), Money::fromDecimalString($amount), overpayment: OverpaymentChoice::KeepAsCredit));

    $at('01 21:00');
    $charge($nadia, $this->sara, '500.00');

    $at('02 09:15');
    $charge($mohamed, $this->sara, '350.00');
    $at('02 10:30');
    $pay($nadia, $this->sara, '200.00');
    $at('02 11:00');
    $wrong = $charge($mohamed, $this->omar, '90.00')->entries[0]->entryId;
    $ledger->void(new VoidRequest($wrong, $this->omar->getKey(), (string) Str::uuid(), CorrectionReason::Undo));
    $at('02 12:45');
    $pay($mohamed, $this->omar, '50.00');

    $at('02 18:00');
});

test('a day\'s book lists every entry, the latest first, and counts only what stands', function () {
    $journal = Livewire::test('accounts::admin.page.journal');

    expect(collect($journal->instance()->entries)->map(fn ($entry) => $entry->kind->value.' '.$entry->amount->toDecimalString().($entry->voided ? ' voided' : ''))->all())
        ->toBe(['payment 50.00', 'reversal 90.00', 'charge 90.00 voided', 'payment 200.00', 'charge 350.00']);

    $totals = $journal->instance()->totals;

    expect($totals->taken->toDecimalString())->toBe('350.00')
        ->and($totals->charges)->toBe(1)
        ->and($totals->collected->toDecimalString())->toBe('250.00')
        ->and($totals->payments)->toBe(2)
        ->and($totals->voided)->toBe(1)
        ->and($totals->change()->toDecimalString())->toBe('100.00');
});

test('each person\'s share of the day, the most cash first — what the drawer is counted against', function () {
    $people = Livewire::test('accounts::admin.page.journal')->instance()->people;

    expect(array_map(fn (array $person) => [$person['name'], $person['totals']->taken->toDecimalString(), $person['totals']->collected->toDecimalString()], $people))
        ->toBe([['سارة', '350.00', '200.00'], ['عمر', '0.00', '50.00']]);
});

test('the book narrows to one person, or to one kind of entry', function () {
    $mine = Livewire::test('accounts::admin.page.journal')->call('choosePerson', $this->omar->getKey());

    expect(collect($mine->instance()->entries)->pluck('by')->unique()->all())->toBe(['عمر']);

    $payments = Livewire::test('accounts::admin.page.journal')->call('chooseKind', 'payment');

    expect(collect($payments->instance()->entries)->map(fn ($entry) => $entry->amount->toDecimalString())->all())->toBe(['50.00', '200.00']);
});

test('the day before is one press back, and the day after today is today', function () {
    $journal = Livewire::test('accounts::admin.page.journal')
        ->call('shift', -1)
        ->assertSet('day', '2026-10-01');

    expect(collect($journal->instance()->entries)->map(fn ($entry) => $entry->amount->toDecimalString())->all())->toBe(['500.00'])
        ->and($journal->instance()->isToday)->toBeFalse();

    $journal->call('shift', 1)->assertSet('day', '');

    Livewire::withQueryParams(['day' => '2026-12-31'])
        ->test('accounts::admin.page.journal')
        ->assertSet('isToday', true);
});

test('the business day follows the hour the owner says it starts', function () {
    // Open past midnight: a sale at 01:30 belongs to the day before.
    app(SettingStore::class)->put(LocalClock::DAY_STARTS_AT, '04:00');
    $this->travelTo(CarbonImmutable::parse('2026-10-03 01:30', 'Africa/Cairo'));

    app(AccountLedgerInterface::class)->charge(new ChargeRequest(
        app(CreateCustomer::class)->handle(['name' => 'متأخر الليل'])->id,
        $this->sara->getKey(),
        (string) Str::uuid(),
        [ChargeLine::free('طلبات', Money::fromDecimalString('40.00'))],
    ));

    $journal = Livewire::test('accounts::admin.page.journal');

    expect($journal->instance()->isToday)->toBeTrue()
        ->and($journal->instance()->totals->taken->toDecimalString())->toBe('390.00');
});

test('the journal reads in Arabic, with the day\'s figures and who took what', function () {
    readingIn('ar');

    $this->get(route('admin.accounts.journal'))
        ->assertOk()
        ->assertSee(__('accounts::module.journal.drawer_title', locale: 'ar'))
        ->assertSee('سارة')
        ->assertSee('محمد أحمد')
        ->assertSee(__('accounts::module.journal.voided', locale: 'ar'));
});
