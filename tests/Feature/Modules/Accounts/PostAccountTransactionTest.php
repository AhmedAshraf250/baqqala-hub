<?php

use App\Foundation\Identity\Models\AdminUser;
use App\Foundation\Money\Money;
use App\Modules\Accounts\Contracts\Enums\TransactionType;
use App\Modules\Accounts\Contracts\Events\AccountEntryPosted;
use App\Modules\Accounts\Contracts\Exceptions\CreditLimitExceeded;
use App\Modules\Accounts\Contracts\Exceptions\OverpaymentNotAllowed;
use App\Modules\Accounts\Database\Models\CustomerAccount;
use App\Modules\Accounts\Domain\Actions\PostAccountTransaction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->action = app(PostAccountTransaction::class);
    $this->postedBy = AdminUser::factory()->create()->getKey();
});

test('taking goods on credit increases what the customer owes', function () {
    $account = CustomerAccount::factory()->create();

    $transaction = $this->action->handle(
        $account,
        TransactionType::Debit,
        Money::fromDecimalString('25.50'),
        $this->postedBy,
        'Two bags of rice',
    );

    expect($transaction->outstanding_after->toDecimalString())->toBe('25.50')
        ->and($account->fresh()->outstanding->toDecimalString())->toBe('25.50')
        ->and($account->fresh()->isInDebt())->toBeTrue();
});

test('paying reduces what the customer owes', function () {
    $account = CustomerAccount::factory()->create();

    $this->action->handle($account, TransactionType::Debit, Money::fromDecimalString('100.00'), $this->postedBy);
    $this->action->handle($account, TransactionType::Credit, Money::fromDecimalString('40.25'), $this->postedBy);

    expect($account->fresh()->outstanding->toDecimalString())->toBe('59.75');
});

test('settling the tab exactly leaves nothing owed', function () {
    $account = CustomerAccount::factory()->create();

    $this->action->handle($account, TransactionType::Debit, Money::fromDecimalString('73.40'), $this->postedBy);
    $this->action->handle($account, TransactionType::Credit, Money::fromDecimalString('73.40'), $this->postedBy);

    $account = $account->fresh();

    expect($account->outstanding->isZero())->toBeTrue()
        ->and($account->isInDebt())->toBeFalse()
        ->and($account->isInCredit())->toBeFalse();
});

test('the cached total always matches the ledger', function () {
    $account = CustomerAccount::factory()->create();

    foreach (['10.10', '20.20', '0.01', '5.55'] as $amount) {
        $this->action->handle($account, TransactionType::Debit, Money::fromDecimalString($amount), $this->postedBy);
    }

    $this->action->handle($account, TransactionType::Credit, Money::fromDecimalString('3.33'), $this->postedBy);

    $account = $account->fresh();

    expect($account->outstanding->toDecimalString())->toBe('32.53')
        ->and($account->calculatedOutstanding()->toDecimalString())->toBe('32.53');
});

test('a debit past the credit limit is refused and writes nothing', function () {
    $account = CustomerAccount::factory()->withCreditLimit('50.00')->create();

    expect(fn () => $this->action->handle(
        $account,
        TransactionType::Debit,
        Money::fromDecimalString('50.01'),
        $this->postedBy,
    ))->toThrow(CreditLimitExceeded::class);

    $account = $account->fresh();

    expect($account->outstanding->isZero())->toBeTrue()
        ->and($account->transactions()->count())->toBe(0);
});

test('a debit up to the credit limit exactly is allowed', function () {
    $account = CustomerAccount::factory()->withCreditLimit('50.00')->create();

    $this->action->handle($account, TransactionType::Debit, Money::fromDecimalString('50.00'), $this->postedBy);

    expect($account->fresh()->outstanding->toDecimalString())->toBe('50.00');
});

test('a credit limit of zero means no ceiling, not no credit', function () {
    $account = CustomerAccount::factory()->withCreditLimit('0.00')->create();

    $this->action->handle($account, TransactionType::Debit, Money::fromDecimalString('999.99'), $this->postedBy);

    expect($account->fresh()->outstanding->toDecimalString())->toBe('999.99');
});

test('paying more than the tab is refused by default', function () {
    $account = CustomerAccount::factory()->create();

    $this->action->handle($account, TransactionType::Debit, Money::fromDecimalString('20.00'), $this->postedBy);

    expect(fn () => $this->action->handle(
        $account,
        TransactionType::Credit,
        Money::fromDecimalString('25.00'),
        $this->postedBy,
    ))->toThrow(OverpaymentNotAllowed::class);

    expect($account->fresh()->outstanding->toDecimalString())->toBe('20.00');
});

test('a shop that takes deposits can accept an overpayment', function () {
    config()->set('accounts.allow_overpayment', true);

    $account = CustomerAccount::factory()->create();

    $this->action->handle($account, TransactionType::Debit, Money::fromDecimalString('20.00'), $this->postedBy);
    $this->action->handle($account, TransactionType::Credit, Money::fromDecimalString('25.00'), $this->postedBy);

    $account = $account->fresh();

    expect($account->outstanding->toDecimalString())->toBe('-5.00')
        ->and($account->isInCredit())->toBeTrue();
});

test('the overpayment setting does not disable the credit limit', function () {
    // These are unrelated rules; one used to silently switch off the other.
    config()->set('accounts.allow_overpayment', true);

    $account = CustomerAccount::factory()->withCreditLimit('50.00')->create();

    expect(fn () => $this->action->handle(
        $account,
        TransactionType::Debit,
        Money::fromDecimalString('60.00'),
        $this->postedBy,
    ))->toThrow(CreditLimitExceeded::class);
});

test('a non-positive amount is rejected', function () {
    $account = CustomerAccount::factory()->create();

    expect(fn () => $this->action->handle($account, TransactionType::Debit, Money::zero(), $this->postedBy))
        ->toThrow(InvalidArgumentException::class);
});

test('a mistake is corrected by posting its inverse, leaving both entries', function () {
    $account = CustomerAccount::factory()->create();

    $mistake = $this->action->handle($account, TransactionType::Debit, Money::fromDecimalString('30.00'), $this->postedBy);

    $this->action->handle(
        $account,
        $mistake->type->inverse(),
        $mistake->amount,
        $this->postedBy,
        'Reversal of '.$mistake->reference,
    );

    $account = $account->fresh();

    expect($account->outstanding->isZero())->toBeTrue()
        // The history stays auditable: nothing was edited or deleted.
        ->and($account->transactions()->count())->toBe(2);
});

test('every posted entry gets a unique reference', function () {
    $account = CustomerAccount::factory()->create();

    $references = collect(range(1, 5))->map(fn () => $this->action->handle(
        $account,
        TransactionType::Debit,
        Money::fromDecimalString('1.00'),
        $this->postedBy,
    )->reference);

    expect($references->unique())->toHaveCount(5);
});

test('posting announces itself so listeners can react', function () {
    Event::fake([AccountEntryPosted::class]);

    $account = CustomerAccount::factory()->create();

    $this->action->handle($account, TransactionType::Debit, Money::fromDecimalString('7.00'), $this->postedBy);

    Event::assertDispatched(AccountEntryPosted::class);
});
