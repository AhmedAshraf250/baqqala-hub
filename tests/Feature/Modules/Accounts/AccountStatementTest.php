<?php

use App\Foundation\Money\Money;
use App\Foundation\Time\LocalClock;
use App\Modules\Accounts\Contracts\AccountLedgerInterface;
use App\Modules\Accounts\Contracts\Data\ChargeLine;
use App\Modules\Accounts\Contracts\Data\ChargeRequest;
use App\Modules\Accounts\Contracts\Data\PaymentRequest;
use App\Modules\Accounts\Contracts\Data\VoidRequest;
use App\Modules\Accounts\Contracts\Enums\CorrectionReason;
use App\Modules\Accounts\Contracts\Enums\EntryKind;
use App\Modules\Accounts\Contracts\Enums\TransactionType;
use App\Modules\Accounts\Database\Models\CustomerAccount;
use App\Modules\Accounts\Domain\Actions\CorrectEntry;
use App\Modules\Accounts\Domain\Actions\RecordOpeningBalance;
use App\Modules\Accounts\Domain\Queries\AccountStatement;
use App\Modules\Accounts\Domain\Queries\StatementEntry;
use App\Modules\Accounts\Domain\Queries\StatementKind;
use App\Modules\Accounts\Domain\Queries\StatementPeriod;
use App\Modules\Customers\Domain\Actions\CreateCustomer;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);

/**
 * Mohamed's book from the design, oldest first — §3.5 of the customer
 * accounts document: an opening balance, a charge written wrong and
 * corrected the next day, a charge undone at once, and 1,250.00 owed.
 */
beforeEach(function () {
    $by = actingAsAdmin()->getKey();
    $ledger = app(AccountLedgerInterface::class);
    $customer = app(CreateCustomer::class)->handle(['name' => 'محمد أحمد علي']);
    $at = fn (string $moment) => $this->travelTo(CarbonImmutable::parse("2026-{$moment}", 'Africa/Cairo'));
    $charge = fn (string $amount, array $lines = []) => $ledger->charge(new ChargeRequest($customer->id, $by, (string) Str::uuid(), $lines ?: [ChargeLine::free('حاجات', Money::fromDecimalString($amount))]));
    $pay = fn (string $amount) => $ledger->payment(new PaymentRequest($customer->id, $by, (string) Str::uuid(), Money::fromDecimalString($amount)));

    $at('09-15 12:00');
    app(RecordOpeningBalance::class)->handle($customer->id, $by, (string) Str::uuid(), Money::fromDecimalString('300.00'), TransactionType::Debit, 'من الكشكول');
    $at('09-21 12:00');
    $charge('250.00');
    $at('09-24 12:00');
    $pay('300.00');
    $at('09-28 19:30');
    $this->wrong = $charge('450.00')->first()->entryId;
    $at('09-29 10:05');
    app(CorrectEntry::class)->handle($this->wrong, $by, (string) Str::uuid(), CorrectionReason::WrongAmount, lines: [ChargeLine::free('حاجات', Money::fromDecimalString('400.00'))]);
    $at('09-30 18:20');
    $pay('150.00');
    $at('10-01 20:05');
    $charge('600.00');
    $at('10-02 09:15');
    $pay('200.00');
    $at('10-02 10:40:00');
    $undone = $charge('35.00')->first()->entryId;
    $at('10-02 10:40:06');
    $ledger->void(new VoidRequest($undone, $by, (string) Str::uuid(), CorrectionReason::Undo));
    $at('10-02 10:42');
    $charge('350.00', array_map(fn (string $name) => ChargeLine::free($name, Money::fromDecimalString('87.50')), ['أرز', 'زيت', 'سكر', 'شاي']));
    $at('10-02 11:00');

    $this->account = CustomerAccount::query()->where('customer_id', $customer->id)->firstOrFail();
    $this->statement = app(AccountStatement::class);
});

test('the book ends where the design says it does', function () {
    expect($this->account->outstanding->toDecimalString())->toBe('1250.00');
});

test('the figures leave out what was voided, and count what corrected it', function () {
    $totals = $this->statement->totals($this->account);

    expect($totals->taken->toDecimalString())->toBe('1600.00')
        ->and($totals->charges)->toBe(4)
        ->and($totals->paid->toDecimalString())->toBe('650.00')
        ->and($totals->payments)->toBe(3)
        ->and($totals->openingBalances->toDecimalString())->toBe('300.00')
        ->and($totals->voided)->toBe(2)
        ->and($totals->entries)->toBe(10)
        ->and($totals->opening->isZero())->toBeTrue()
        ->and($totals->closing->toDecimalString())->toBe('1250.00');
});

test('a period starts from the balance before it, and ends at the one after', function () {
    $bounds = StatementPeriod::Between->bounds(app(LocalClock::class), '2026-10-02', '2026-10-01');
    $totals = $this->statement->totals($this->account, $bounds);

    expect($totals->opening->toDecimalString())->toBe('500.00')
        ->and($totals->closing->toDecimalString())->toBe('1250.00')
        ->and($totals->taken->toDecimalString())->toBe('950.00')
        ->and($totals->charges)->toBe(2)
        ->and($totals->paid->toDecimalString())->toBe('200.00');
});

test('the statement reads newest first, a day at a time, each day with where it ended', function () {
    ['days' => $days, 'older' => $older] = $this->statement->days($this->account, [null, null]);

    expect($older)->toBeFalse()
        ->and(array_map(fn ($day) => $day->date->format('m-d'), $days))->toBe(['10-02', '10-01', '09-30', '09-29', '09-28', '09-24', '09-21', '09-15'])
        ->and(array_map(fn ($day) => $day->closing?->toDecimalString(), $days))->toBe(['1250.00', '1100.00', '500.00', '650.00', '700.00', '250.00', '550.00', '300.00']);

    $today = $days[0]->entries;

    expect(array_map(fn (StatementEntry $entry) => $entry->amount->toDecimalString(), $today))->toBe(['350.00', '35.00', '200.00'])
        ->and($today[0]->lines)->toBe(['أرز', 'زيت', 'سكر', 'شاي'])
        ->and($today[0]->before->toDecimalString())->toBe('900.00')
        ->and($today[0]->after->toDecimalString())->toBe('1250.00');
});

test('an entry undone at once reads as one line, with who undid it and how soon', function () {
    $undone = $this->statement->days($this->account, [null, null])['days'][0]->entries[1];

    expect($undone->kind)->toBe(EntryKind::Charge)
        ->and($undone->undone['seconds'])->toBe(6)
        ->and($undone->isVoided())->toBeTrue();
});

test('a corrected entry stays, voided, and the void and its replacement read on the day they were made', function () {
    $days = collect($this->statement->days($this->account, [null, null])['days'])->keyBy(fn ($day) => $day->date->format('m-d'));

    [$replacement, $reversal] = $days['09-29']->entries;
    [$original] = $days['09-28']->entries;

    expect($replacement->corrects['id'])->toBe($this->wrong)
        ->and($replacement->amount->toDecimalString())->toBe('400.00')
        ->and($reversal->kind)->toBe(EntryKind::Reversal)
        ->and($reversal->cancels['id'])->toBe($this->wrong)
        ->and($reversal->reason)->toBe(CorrectionReason::WrongAmount)
        ->and($original->isVoided())->toBeTrue()
        ->and($original->voided['reason'])->toBe(CorrectionReason::WrongAmount)
        ->and($original->corrected)->toBeTrue();
});

test('narrowed to one kind of entry, it shows only those, with no day\'s end it cannot vouch for', function () {
    $days = $this->statement->days($this->account, [null, null], StatementKind::Payments)['days'];

    expect(collect($days)->flatMap(fn ($day) => $day->entries)->map(fn ($entry) => $entry->amount->toDecimalString())->all())->toBe(['200.00', '150.00', '300.00'])
        ->and(collect($days)->pluck('closing')->filter()->all())->toBe([]);
});

test('a page at a time, and it says when there is more', function () {
    ['days' => $days, 'older' => $older] = $this->statement->days($this->account, [null, null], limit: 4);

    expect($older)->toBeTrue()
        ->and(collect($days)->flatMap(fn ($day) => $day->entries)->count())->toBeLessThanOrEqual(4);
});

test('the periods are business days, ending today', function () {
    $clock = app(LocalClock::class);

    expect(StatementPeriod::ThisMonth->days($clock)[0]->format('Y-m-d'))->toBe('2026-10-01')
        ->and(StatementPeriod::Last30Days->days($clock)[0]->format('Y-m-d'))->toBe('2026-09-03')
        ->and(StatementPeriod::Everything->days($clock))->toBe([null, null])
        ->and(StatementPeriod::Between->days($clock, 'nonsense', null))->toBe([null, null]);
});
