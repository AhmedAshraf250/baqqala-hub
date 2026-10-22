<?php

use App\Foundation\Identity\Models\AdminUser;
use App\Foundation\Money\Money;
use App\Modules\Accounts\Contracts\AccountLedgerInterface;
use App\Modules\Accounts\Contracts\Data\ChargeLine;
use App\Modules\Accounts\Contracts\Data\ChargeRequest;
use App\Modules\Accounts\Contracts\Data\PaymentRequest;
use App\Modules\Accounts\Contracts\Data\VoidRequest;
use App\Modules\Accounts\Contracts\Enums\CorrectionReason;
use App\Modules\Accounts\Contracts\Enums\EntryKind;
use App\Modules\Accounts\Contracts\Enums\OverpaymentChoice;
use App\Modules\Accounts\Contracts\Enums\TransactionType;
use App\Modules\Accounts\Contracts\Exceptions\AccountChangedMeanwhile;
use App\Modules\Accounts\Contracts\Exceptions\EntryAlreadyVoided;
use App\Modules\Accounts\Contracts\Exceptions\NothingOwed;
use App\Modules\Accounts\Contracts\Exceptions\OverpaymentNotAllowed;
use App\Modules\Accounts\Contracts\Exceptions\PriceChangedMeanwhile;
use App\Modules\Accounts\Contracts\Exceptions\ProductUnavailable;
use App\Modules\Accounts\Contracts\Exceptions\ReversalCannotBeVoided;
use App\Modules\Accounts\Database\Models\AccountTransaction;
use App\Modules\Accounts\Database\Models\CustomerAccount;
use App\Modules\Accounts\Domain\Actions\CorrectEntry;
use App\Modules\Accounts\Domain\Actions\RecordDiscount;
use App\Modules\Accounts\Domain\Actions\RecordOpeningBalance;
use App\Modules\Catalog\Database\Models\Product;
use App\Modules\Customers\Database\Models\Customer;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->ledger = app(AccountLedgerInterface::class);
    $this->cashier = AdminUser::factory()->create();
    $this->customer = Customer::factory()->create();
});

/**
 * A charge for the test's customer, posted by the test's cashier.
 *
 * @param  list<ChargeLine>  $lines
 */
function charge(array $lines, ?Money $paidNow = null, ?string $operationId = null): ChargeRequest
{
    return new ChargeRequest(test()->customer->getKey(), test()->cashier->getKey(), $operationId ?? (string) Str::uuid(), $lines, paidNow: $paidNow);
}

function money(string $amount): Money
{
    return Money::fromDecimalString($amount);
}

function balanceOf(Customer $customer): string
{
    return CustomerAccount::query()->where('customer_id', $customer->getKey())->firstOrFail()->outstanding->toDecimalString();
}

test('a charge is priced from the catalog, line by line, and keeps what it was sold at', function () {
    // The example from the design: rice 5 kg × 2, oil × 1, biscuits × 3 = 435.
    $rice = Product::factory()->priced('150.00')->create(['name' => 'أرز الضحى 5 كجم']);
    $oil = Product::factory()->priced('90.00')->create(['name' => 'زيت كريستال 1 لتر']);
    $biscuits = Product::factory()->priced('15.00')->create(['name' => 'بسكويت أوريو']);

    $posted = $this->ledger->charge(charge([
        ChargeLine::product($rice->getKey(), 2000),
        ChargeLine::product($oil->getKey(), 1000),
        ChargeLine::product($biscuits->getKey(), 3000),
    ]));

    $entry = AccountTransaction::query()->findOrFail($posted->first()->entryId);

    expect($posted->outstandingAfter()->toDecimalString())->toBe('435.00')
        ->and($entry->kind)->toBe(EntryKind::Charge)
        ->and($entry->items->pluck('name')->all())->toBe(['أرز الضحى 5 كجم', 'زيت كريستال 1 لتر', 'بسكويت أوريو'])
        ->and($entry->items->map(fn ($item) => $item->line_total->toDecimalString())->all())->toBe(['300.00', '90.00', '45.00']);

    // The catalog changes; the statement does not.
    $rice->update(['name' => 'أرز الضحى 5 كيلو', 'price' => money('165.00')]);

    expect($entry->fresh()->items->first()->name)->toBe('أرز الضحى 5 كجم')
        ->and($entry->fresh()->items->first()->unit_price->toDecimalString())->toBe('150.00');
});

test('a price changed at the counter is charged, and the catalog price is kept beside it', function () {
    $sugar = Product::factory()->byWeight()->priced('36.00')->create();

    $posted = $this->ledger->charge(charge([ChargeLine::product($sugar->getKey(), 2000, unitPrice: money('35.00'))]));

    $line = AccountTransaction::query()->findOrFail($posted->first()->entryId)->items->first();

    expect($line->line_total->toDecimalString())->toBe('70.00')
        ->and($line->list_price->toDecimalString())->toBe('36.00')
        ->and($line->priceWasChanged())->toBeTrue();
});

test('a loose product is sold by weight or by what it costs', function () {
    $cheese = Product::factory()->byWeight()->priced('140.00')->create();
    $seeds = Product::factory()->byWeight()->priced('220.00')->create();

    $posted = $this->ledger->charge(charge([
        // A quarter kilo of cheese.
        ChargeLine::product($cheese->getKey(), 250),
        // «لب بـ10 جنيه»: the total is what was asked for, the weight follows.
        ChargeLine::productByAmount($seeds->getKey(), money('10.00')),
    ]));

    $lines = AccountTransaction::query()->findOrFail($posted->first()->entryId)->items;

    expect($lines[0]->line_total->toDecimalString())->toBe('35.00')
        ->and($lines[1]->line_total->toDecimalString())->toBe('10.00')
        // 10 ÷ 220 per kilo is about 45 grams.
        ->and($lines[1]->quantity)->toBe(45)
        ->and($posted->outstandingAfter()->toDecimalString())->toBe('45.00');
});

test('a line rounds to the piastre on its own, so the total is the sum of what is shown', function () {
    $cheese = Product::factory()->byWeight()->priced('37.50')->create();

    // 37.50 × 0.333 = 12.4875 → 12.49.
    $posted = $this->ledger->charge(charge([ChargeLine::product($cheese->getKey(), 333)]));

    expect($posted->outstandingAfter()->toDecimalString())->toBe('12.49');
});

test('a free line needs no product: «350, miscellaneous»', function () {
    $posted = $this->ledger->charge(charge([ChargeLine::free('حاجات متفرقة', money('350.00'))]));

    $line = AccountTransaction::query()->findOrFail($posted->first()->entryId)->items->first();

    expect($line->product_id)->toBeNull()
        ->and($line->name)->toBe('حاجات متفرقة')
        ->and($line->list_price)->toBeNull()
        ->and($posted->outstandingAfter()->toDecimalString())->toBe('350.00');
});

test('a piece is never split, and a piece is not sold by amount', function () {
    $oil = Product::factory()->priced('90.00')->create();

    expect(fn () => $this->ledger->charge(charge([ChargeLine::product($oil->getKey(), 1500)])))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $this->ledger->charge(charge([ChargeLine::productByAmount($oil->getKey(), money('45.00'))])))->toThrow(InvalidArgumentException::class);
});

test('a product archived after it was picked, or a price changed after it was seen, is refused', function () {
    $tea = Product::factory()->priced('75.00')->create();

    expect(fn () => $this->ledger->charge(charge([ChargeLine::product($tea->getKey(), 1000, expectedListPrice: money('70.00'))])))
        ->toThrow(PriceChangedMeanwhile::class);

    $tea->update(['is_active' => false]);

    expect(fn () => $this->ledger->charge(charge([ChargeLine::product($tea->getKey(), 1000)])))->toThrow(ProductUnavailable::class)
        ->and(AccountTransaction::query()->count())->toBe(0);
});

test('a charge that comes to nothing is refused', function () {
    $gift = Product::factory()->priced('0.00')->create();

    expect(fn () => $this->ledger->charge(charge([ChargeLine::product($gift->getKey(), 1000)])))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $this->ledger->charge(charge([])))->toThrow(InvalidArgumentException::class);
});

test('what was paid on the spot is posted with the charge, in one operation', function () {
    $posted = $this->ledger->charge(charge([ChargeLine::free('حاجات', money('350.00'))], paidNow: money('100.00')));

    expect($posted->entries)->toHaveCount(2)
        ->and($posted->entries[0]->kind)->toBe(EntryKind::Charge)
        ->and($posted->entries[1]->kind)->toBe(EntryKind::Payment)
        ->and($posted->outstandingAfter()->toDecimalString())->toBe('250.00')
        ->and(AccountTransaction::query()->where('operation_id', $posted->operationId)->count())->toBe(2);
});

test('the same operation arriving twice is posted once', function () {
    // A double click, or a retry after the network dropped.
    $operation = (string) Str::uuid();

    $first = $this->ledger->charge(charge([ChargeLine::free('حاجات', money('50.00'))], operationId: $operation));
    $again = $this->ledger->charge(charge([ChargeLine::free('حاجات', money('50.00'))], operationId: $operation));

    expect($again->replayed)->toBeTrue()
        ->and($first->replayed)->toBeFalse()
        ->and($again->first()->entryId)->toBe($first->first()->entryId)
        ->and(AccountTransaction::query()->count())->toBe(1)
        ->and(balanceOf($this->customer))->toBe('50.00');
});

test('a payment larger than the debt is settled by the choice made at the counter', function () {
    $this->ledger->charge(charge([ChargeLine::free('حاجات', money('420.00'))]));

    $pay = fn (OverpaymentChoice $choice): PaymentRequest => new PaymentRequest($this->customer->getKey(), $this->cashier->getKey(), (string) Str::uuid(), money('500.00'), overpayment: $choice);

    expect(fn () => $this->ledger->payment($pay(OverpaymentChoice::Refuse)))->toThrow(OverpaymentNotAllowed::class);

    // The 80 went back as change.
    $owedOnly = $this->ledger->payment($pay(OverpaymentChoice::RecordOwedOnly));

    expect($owedOnly->first()->amount->toDecimalString())->toBe('420.00')
        ->and($owedOnly->outstandingAfter()->isZero())->toBeTrue();

    // Nothing is owed now, so «only what is owed» has nothing to record…
    expect(fn () => $this->ledger->payment($pay(OverpaymentChoice::RecordOwedOnly)))->toThrow(NothingOwed::class);

    // …and kept as credit, it all stays with the shop.
    expect($this->ledger->payment($pay(OverpaymentChoice::KeepAsCredit))->outstandingAfter()->toDecimalString())->toBe('-500.00');
});

test('a payment decided on a balance someone has since changed is refused, with the new balance', function () {
    $charge = $this->ledger->charge(charge([ChargeLine::free('حاجات', money('500.00'))]));

    // Seen by one cashier; meanwhile a colleague records a payment of 200.
    $seen = $charge->last()->entryId;
    $this->ledger->payment(new PaymentRequest($this->customer->getKey(), $this->cashier->getKey(), (string) Str::uuid(), money('200.00')));

    try {
        $this->ledger->payment(new PaymentRequest($this->customer->getKey(), $this->cashier->getKey(), (string) Str::uuid(), money('500.00'), expectedLastEntryId: $seen));

        $this->fail('The stale payment should have been refused.');
    } catch (AccountChangedMeanwhile $refusal) {
        expect($refusal->outstanding->toDecimalString())->toBe('300.00');
    }

    expect(balanceOf($this->customer))->toBe('300.00');
});

test('voiding posts a reversal and leaves the entry where it was', function () {
    $charge = $this->ledger->charge(charge([ChargeLine::free('حاجات', money('35.00'))]));

    $void = $this->ledger->void(new VoidRequest($charge->first()->entryId, $this->cashier->getKey(), (string) Str::uuid(), CorrectionReason::Duplicate));

    $reversal = AccountTransaction::query()->findOrFail($void->first()->entryId);
    $original = AccountTransaction::query()->findOrFail($charge->first()->entryId);

    expect($reversal->kind)->toBe(EntryKind::Reversal)
        ->and($reversal->type)->toBe(TransactionType::Credit)
        ->and($reversal->reverses_id)->toBe($original->getKey())
        ->and($reversal->reason)->toBe(CorrectionReason::Duplicate)
        ->and($original->amount->toDecimalString())->toBe('35.00')
        ->and($original->reversal->is($reversal))->toBeTrue()
        ->and(balanceOf($this->customer))->toBe('0.00');
});

test('an entry is voided once, and a reversal is never voided', function () {
    $charge = $this->ledger->charge(charge([ChargeLine::free('حاجات', money('35.00'))]));
    $void = fn (int $entryId) => $this->ledger->void(new VoidRequest($entryId, $this->cashier->getKey(), (string) Str::uuid(), CorrectionReason::Duplicate));

    $reversal = $void($charge->first()->entryId);

    expect(fn () => $void($charge->first()->entryId))->toThrow(EntryAlreadyVoided::class)
        ->and(fn () => $void($reversal->first()->entryId))->toThrow(ReversalCannotBeVoided::class);

    // Even past the check, the database refuses a second reversal of it.
    expect(fn () => DB::table('account_transactions')->insert([
        'account_id' => CustomerAccount::query()->value('id'), 'type' => 'credit', 'kind' => 'reversal', 'amount' => 3500,
        'outstanding_after' => -3500, 'reference' => 'TRX-RACE', 'created_by' => $this->cashier->getKey(),
        'reverses_id' => $charge->first()->entryId, 'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('voiding a payment brings back the payment before it as the latest', function () {
    $this->ledger->charge(charge([ChargeLine::free('حاجات', money('500.00'))]));

    $this->travelTo(now()->setDateTime(2026, 9, 1, 9, 0));
    $this->ledger->payment(new PaymentRequest($this->customer->getKey(), $this->cashier->getKey(), (string) Str::uuid(), money('100.00')));

    $this->travelTo(now()->setDateTime(2026, 9, 20, 9, 0));
    $mistake = $this->ledger->payment(new PaymentRequest($this->customer->getKey(), $this->cashier->getKey(), (string) Str::uuid(), money('100.00')));
    $this->ledger->void(new VoidRequest($mistake->first()->entryId, $this->cashier->getKey(), (string) Str::uuid(), CorrectionReason::WrongCustomer));

    expect(CustomerAccount::query()->firstOrFail()->last_payment_at?->toDateTimeString())->toBe('2026-09-01 09:00:00');
});

test('correcting an entry cancels it and posts the right one, pointing back at it', function () {
    $rice = Product::factory()->priced('150.00')->create();
    $wrong = $this->ledger->charge(charge([ChargeLine::product($rice->getKey(), 3000)]));

    $corrected = app(CorrectEntry::class)->handle(
        $wrong->first()->entryId,
        $this->cashier->getKey(),
        (string) Str::uuid(),
        CorrectionReason::WrongItems,
        lines: [ChargeLine::product($rice->getKey(), 2000)],
    );

    [$reversal, $replacement] = $corrected->entries;

    expect($reversal->kind)->toBe(EntryKind::Reversal)
        ->and($replacement->kind)->toBe(EntryKind::Charge)
        ->and(AccountTransaction::query()->findOrFail($replacement->entryId)->replaces_id)->toBe($wrong->first()->entryId)
        ->and($corrected->outstandingAfter()->toDecimalString())->toBe('300.00')
        ->and(AccountTransaction::query()->count())->toBe(3);
});

test('an entry recorded on the wrong customer moves to the right one', function () {
    $right = Customer::factory()->create();
    $entry = $this->ledger->charge(charge([ChargeLine::free('حاجات', money('60.00'))]));

    app(CorrectEntry::class)->handle(
        $entry->first()->entryId,
        $this->cashier->getKey(),
        (string) Str::uuid(),
        CorrectionReason::WrongCustomer,
        toCustomerId: $right->getKey(),
        lines: [ChargeLine::free('حاجات', money('60.00'))],
    );

    expect(balanceOf($this->customer))->toBe('0.00')
        ->and(balanceOf($right))->toBe('60.00');
});

test('an opening balance comes either way, and a discount lowers the debt', function () {
    $opening = app(RecordOpeningBalance::class)->handle($this->customer->getKey(), $this->cashier->getKey(), (string) Str::uuid(), money('300.00'), TransactionType::Debit, 'من الكشكول');

    expect($opening->first()->kind)->toBe(EntryKind::OpeningBalance)
        ->and(balanceOf($this->customer))->toBe('300.00');

    app(RecordDiscount::class)->handle($this->customer->getKey(), $this->cashier->getKey(), (string) Str::uuid(), money('2.50'));

    expect(balanceOf($this->customer))->toBe('297.50');

    $credit = Customer::factory()->create();
    app(RecordOpeningBalance::class)->handle($credit->getKey(), $this->cashier->getKey(), (string) Str::uuid(), money('50.00'), TransactionType::Credit);

    expect(balanceOf($credit))->toBe('-50.00');
});

test('whoever recorded an entry may correct it for fifteen minutes; after that, or anyone else\'s, takes the permission', function () {
    $this->seed(AuthorizationSeeder::class);

    $cashier = AdminUser::factory()->create()->assignRole('cashier');
    $colleague = AdminUser::factory()->create()->assignRole('cashier');
    $manager = AdminUser::factory()->create()->assignRole('manager');

    $entry = AccountTransaction::query()->findOrFail(app(AccountLedgerInterface::class)->charge(
        new ChargeRequest($this->customer->getKey(), $cashier->getKey(), (string) Str::uuid(), [ChargeLine::free('حاجات', money('35.00'))]),
    )->first()->entryId);

    expect($cashier->can('correct', $entry))->toBeTrue()
        ->and($colleague->can('correct', $entry))->toBeFalse()
        ->and($manager->can('correct', $entry))->toBeTrue();

    $this->travel(16)->minutes();

    expect($cashier->can('correct', $entry))->toBeFalse()
        ->and($manager->can('correct', $entry))->toBeTrue();
});
