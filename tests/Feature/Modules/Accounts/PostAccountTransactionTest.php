<?php

use App\Foundation\Identity\Models\AdminUser;
use App\Foundation\Money\Money;
use App\Modules\Accounts\Contracts\Enums\EntryKind;
use App\Modules\Accounts\Contracts\Enums\TransactionType;
use App\Modules\Accounts\Contracts\Events\AccountEntryPosted;
use App\Modules\Accounts\Contracts\Exceptions\CreditLimitExceeded;
use App\Modules\Accounts\Contracts\Exceptions\OverpaymentNotAllowed;
use App\Modules\Accounts\Database\Models\AccountTransaction;
use App\Modules\Accounts\Database\Models\CustomerAccount;
use App\Modules\Accounts\Domain\Actions\PostAccountTransaction;
use App\Modules\Accounts\Domain\Exceptions\DiscountExceedsBalance;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->action = app(PostAccountTransaction::class);
    $this->postedBy = AdminUser::factory()->create()->getKey();

    $this->post = fn (CustomerAccount $account, EntryKind $kind, string $amount, ?TransactionType $type = null): AccountTransaction => $this->action->handle(
        $account,
        $kind,
        Money::fromDecimalString($amount),
        $this->postedBy,
        type: $type,
    );
});

test('taking goods on credit increases what the customer owes', function () {
    $account = CustomerAccount::factory()->create();

    $transaction = ($this->post)($account, EntryKind::Charge, '25.50');

    expect($transaction->outstanding_after->toDecimalString())->toBe('25.50')
        ->and($transaction->type)->toBe(TransactionType::Debit)
        ->and($account->fresh()->outstanding->toDecimalString())->toBe('25.50')
        ->and($account->fresh()->isInDebt())->toBeTrue();
});

test('paying reduces what the customer owes', function () {
    $account = CustomerAccount::factory()->create();

    ($this->post)($account, EntryKind::Charge, '100.00');
    ($this->post)($account, EntryKind::Payment, '40.25');

    expect($account->fresh()->outstanding->toDecimalString())->toBe('59.75');
});

test('settling the tab exactly leaves nothing owed', function () {
    $account = CustomerAccount::factory()->create();

    ($this->post)($account, EntryKind::Charge, '73.40');
    ($this->post)($account, EntryKind::Payment, '73.40');

    $account = $account->fresh();

    expect($account->outstanding->isZero())->toBeTrue()
        ->and($account->isInDebt())->toBeFalse()
        ->and($account->isInCredit())->toBeFalse();
});

test('the cached total always matches the ledger', function () {
    $account = CustomerAccount::factory()->create();

    foreach (['10.10', '20.20', '0.01', '5.55'] as $amount) {
        ($this->post)($account, EntryKind::Charge, $amount);
    }

    ($this->post)($account, EntryKind::Payment, '3.33');

    $account = $account->fresh();

    expect($account->outstanding->toDecimalString())->toBe('32.53')
        ->and($account->calculatedOutstanding()->toDecimalString())->toBe('32.53');
});

test('a charge past the credit limit is refused and writes nothing', function () {
    $account = CustomerAccount::factory()->withCreditLimit('50.00')->create();

    expect(fn () => ($this->post)($account, EntryKind::Charge, '50.01'))->toThrow(CreditLimitExceeded::class);

    $account = $account->fresh();

    expect($account->outstanding->isZero())->toBeTrue()
        ->and($account->transactions()->count())->toBe(0);
});

test('a charge up to the credit limit exactly is allowed', function () {
    $account = CustomerAccount::factory()->withCreditLimit('50.00')->create();

    ($this->post)($account, EntryKind::Charge, '50.00');

    expect($account->fresh()->outstanding->toDecimalString())->toBe('50.00');
});

test('a credit limit of zero means no ceiling, not no credit', function () {
    $account = CustomerAccount::factory()->withCreditLimit('0.00')->create();

    ($this->post)($account, EntryKind::Charge, '999.99');

    expect($account->fresh()->outstanding->toDecimalString())->toBe('999.99');
});

test('what the paper book says was owed is not held to the credit limit', function () {
    // It already happened; refusing it would leave the ledger wrong, not the
    // customer within their limit.
    $account = CustomerAccount::factory()->withCreditLimit('50.00')->create();

    ($this->post)($account, EntryKind::OpeningBalance, '300.00', TransactionType::Debit);

    expect($account->fresh()->outstanding->toDecimalString())->toBe('300.00');
});

test('paying more than the tab is refused when the shop takes no deposits', function () {
    config()->set('accounts.allow_overpayment', false);

    $account = CustomerAccount::factory()->create();

    ($this->post)($account, EntryKind::Charge, '20.00');

    expect(fn () => ($this->post)($account, EntryKind::Payment, '25.00'))->toThrow(OverpaymentNotAllowed::class);

    expect($account->fresh()->outstanding->toDecimalString())->toBe('20.00');
});

test('a shop that takes deposits keeps the rest as credit', function () {
    // The default since the owner chose to be asked at the counter.
    expect(config('accounts.allow_overpayment'))->toBeTrue();

    $account = CustomerAccount::factory()->create();

    ($this->post)($account, EntryKind::Charge, '20.00');
    ($this->post)($account, EntryKind::Payment, '25.00');

    $account = $account->fresh();

    expect($account->outstanding->toDecimalString())->toBe('-5.00')
        ->and($account->isInCredit())->toBeTrue();
});

test('the overpayment setting does not disable the credit limit', function () {
    // These are unrelated rules; one used to silently switch off the other.
    config()->set('accounts.allow_overpayment', true);

    $account = CustomerAccount::factory()->withCreditLimit('50.00')->create();

    expect(fn () => ($this->post)($account, EntryKind::Charge, '60.00'))->toThrow(CreditLimitExceeded::class);
});

test('a discount lowers what is owed, and never below zero', function () {
    $account = CustomerAccount::factory()->create();

    ($this->post)($account, EntryKind::Charge, '352.50');
    ($this->post)($account, EntryKind::Payment, '350.00');
    ($this->post)($account, EntryKind::Discount, '2.50');

    expect($account->fresh()->outstanding->isZero())->toBeTrue()
        ->and(fn () => ($this->post)($account, EntryKind::Discount, '0.01'))->toThrow(DiscountExceedsBalance::class);
});

test('a non-positive amount is rejected', function () {
    $account = CustomerAccount::factory()->create();

    expect(fn () => $this->action->handle($account, EntryKind::Charge, Money::zero(), $this->postedBy))
        ->toThrow(InvalidArgumentException::class);
});

test('a kind that can go either way has to say which', function () {
    $account = CustomerAccount::factory()->create();

    expect(fn () => ($this->post)($account, EntryKind::OpeningBalance, '10.00'))->toThrow(InvalidArgumentException::class)
        // And a kind that always goes one way cannot be told the other.
        ->and(fn () => ($this->post)($account, EntryKind::Charge, '10.00', TransactionType::Credit))->toThrow(LogicException::class);
});

test('lines that do not add up to the amount are refused', function () {
    $account = CustomerAccount::factory()->create();

    expect(fn () => $this->action->handle($account, EntryKind::Charge, Money::fromDecimalString('10.00'), $this->postedBy, items: [[
        'product_id' => null, 'name' => 'Rice', 'unit' => null, 'quantity' => 1000,
        'unit_price' => Money::fromDecimalString('9.00'), 'list_price' => null, 'line_total' => Money::fromDecimalString('9.00'),
    ]]))->toThrow(LogicException::class);

    expect($account->transactions()->count())->toBe(0);
});

test('an entry is never edited or deleted', function () {
    $account = CustomerAccount::factory()->create();
    $entry = ($this->post)($account, EntryKind::Charge, '30.00');

    expect(fn () => $entry->update(['amount' => Money::fromDecimalString('3.00')]))->toThrow(LogicException::class)
        ->and(fn () => $entry->delete())->toThrow(LogicException::class)
        ->and($entry->fresh()->amount->toDecimalString())->toBe('30.00');
});

test('every posted entry gets a unique reference', function () {
    $account = CustomerAccount::factory()->create();

    $references = collect(range(1, 5))->map(fn () => ($this->post)($account, EntryKind::Charge, '1.00')->reference);

    expect($references->unique())->toHaveCount(5);
});

test('posting announces itself, with the entry and its kind', function () {
    Event::fake([AccountEntryPosted::class]);

    $account = CustomerAccount::factory()->create();
    $entry = ($this->post)($account, EntryKind::Charge, '7.00');

    Event::assertDispatched(AccountEntryPosted::class, fn (AccountEntryPosted $event): bool => $event->entryId === $entry->getKey()
        && $event->kind === EntryKind::Charge
        && $event->customerId === $account->customer_id);
});

test('the account keeps a summary of its ledger with every entry', function () {
    $account = CustomerAccount::factory()->create();

    $this->travelTo(now()->setDateTime(2026, 9, 1, 10, 0));
    $first = ($this->post)($account, EntryKind::Charge, '100.00');

    $account->refresh();

    expect($account->last_entry_id)->toBe($first->getKey())
        ->and($account->in_debt_since?->toDateTimeString())->toBe('2026-09-01 10:00:00')
        ->and($account->last_payment_at)->toBeNull();

    // Paid in part: still owed since the first charge.
    $this->travelTo(now()->setDateTime(2026, 9, 5, 10, 0));
    ($this->post)($account, EntryKind::Payment, '40.00');

    expect($account->refresh()->in_debt_since?->toDateTimeString())->toBe('2026-09-01 10:00:00')
        ->and($account->last_payment_at?->toDateTimeString())->toBe('2026-09-05 10:00:00');

    // Settled: owed since nothing. Then owed again, from the new charge.
    ($this->post)($account, EntryKind::Payment, '60.00');

    expect($account->refresh()->in_debt_since)->toBeNull();

    $this->travelTo(now()->setDateTime(2026, 9, 9, 18, 30));
    $latest = ($this->post)($account, EntryKind::Charge, '15.00');

    expect($account->refresh()->in_debt_since?->toDateTimeString())->toBe('2026-09-09 18:30:00')
        ->and($account->last_entry_id)->toBe($latest->getKey())
        ->and($account->last_entry_at?->toDateTimeString())->toBe('2026-09-09 18:30:00');
});
