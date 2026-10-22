<?php

use App\Foundation\Identity\Models\AdminUser;
use App\Foundation\Modules\ModuleRegistry;
use App\Foundation\Money\Money;
use App\Modules\Accounts\Contracts\AccountLedgerInterface;
use App\Modules\Accounts\Contracts\Data\ChargeLine;
use App\Modules\Accounts\Contracts\Data\ChargeRequest;
use App\Modules\Accounts\Contracts\Data\PaymentRequest;
use App\Modules\Accounts\Contracts\Data\VoidRequest;
use App\Modules\Accounts\Contracts\Enums\CorrectionReason;
use App\Modules\Accounts\Database\Models\CustomerAccount;
use App\Modules\Accounts\Domain\Services\ReconcileAccountTotals;
use App\Modules\Customers\Database\Models\Customer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->customer = Customer::factory()->create();
    $postedBy = AdminUser::factory()->create()->getKey();
    $ledger = app(AccountLedgerInterface::class);

    // A ledger with everything in it: charges, a payment, a void.
    $charge = $ledger->charge(new ChargeRequest($this->customer->getKey(), $postedBy, (string) Str::uuid(), [ChargeLine::free('حاجات', Money::fromDecimalString('500.00'))]));
    $ledger->payment(new PaymentRequest($this->customer->getKey(), $postedBy, (string) Str::uuid(), Money::fromDecimalString('200.00')));
    $mistake = $ledger->charge(new ChargeRequest($this->customer->getKey(), $postedBy, (string) Str::uuid(), [ChargeLine::free('حاجات', Money::fromDecimalString('35.00'))]));
    $ledger->void(new VoidRequest($mistake->first()->entryId, $postedBy, (string) Str::uuid(), CorrectionReason::Undo));

    $this->step = app(ReconcileAccountTotals::class);
});

test('a ledger posted through the application is in step with its account', function () {
    expect($this->step->findings())->toBe([]);
});

test('a balance written behind the ledger\'s back is reported, and put back from the ledger', function () {
    DB::table('customer_accounts')->update(['outstanding' => 99900, 'in_debt_since' => null]);

    expect($this->step->findings())->toHaveCount(1)
        ->and($this->step->findings()[0][1])->toContain('balance 999.00, but its ledger adds up to 300.00');

    $this->step->fix();

    expect($this->step->findings())->toBe([])
        ->and(CustomerAccount::query()->firstOrFail()->outstanding->toDecimalString())->toBe('300.00');
});

test('an entry changed outside the application is reported, and no fix pretends to mend it', function () {
    // An entry's own balance edited in the database: the chain no longer
    // follows, and only a person can find out why.
    DB::table('account_transactions')->where('id', DB::table('account_transactions')->min('id'))->update(['outstanding_after' => 40000]);

    expect(collect($this->step->findings())->map(fn (array $finding) => $finding[1])->all())
        ->toContain('its balance does not follow from the entry before it');

    expect(fn () => $this->step->fix())->toThrow(RuntimeException::class, 'cannot mend it');
});

test('it reports nothing when asked about another module', function () {
    DB::table('customer_accounts')->update(['outstanding' => 1]);

    $catalog = app(ModuleRegistry::class)->all()->get('catalog');

    expect($this->step->findings($catalog))->toBe([])
        ->and($this->step->findings())->not->toBe([]);
});
