<?php

use App\Foundation\Identity\Models\AdminUser;
use App\Foundation\Money\Money;
use App\Modules\Accounts\Contracts\AccountLedgerInterface;
use App\Modules\Accounts\Contracts\AccountRepositoryInterface;
use App\Modules\Accounts\Contracts\Data\AccountStanding;
use App\Modules\Accounts\Contracts\Enums\TransactionType;
use App\Modules\Accounts\Contracts\Events\AccountEntryPosted;
use App\Modules\Accounts\Contracts\Exceptions\CreditLimitExceeded;
use App\Modules\Accounts\Database\Models\CustomerAccount;
use App\Modules\Customers\Contracts\CustomerRepositoryInterface;
use App\Modules\Customers\Contracts\Data\CustomerData;
use App\Modules\Customers\Contracts\Events\CustomerAdded;
use App\Modules\Customers\Database\Models\Customer;
use App\Modules\Customers\Domain\Actions\CreateCustomer;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

uses(LazilyRefreshDatabase::class);

test('a customer crosses the boundary as data, not as a record', function () {
    $customer = app(CreateCustomer::class)->handle(['name' => 'أم أحمد', 'phone' => '01000000001']);

    $data = app(CustomerRepositoryInterface::class)->find($customer->id);

    expect($data)->toBeInstanceOf(CustomerData::class)
        ->and($data->name)->toBe('أم أحمد')
        // What a DTO cannot do, and a model could: change or delete the record.
        ->and(method_exists($data, 'save'))->toBeFalse()
        ->and(method_exists($data, 'delete'))->toBeFalse()
        ->and((new ReflectionClass($data))->isReadOnly())->toBeTrue();
});

test('the directory reads many customers in one query', function () {
    // Exists so a caller listing rows does not fetch them one at a time — the
    // N+1 that holding a plain id instead of a relation would otherwise invite.
    $ids = collect(range(1, 5))
        ->map(fn (int $i) => app(CreateCustomer::class)->handle(['name' => "Customer {$i}"])->id)
        ->all();

    DB::enableQueryLog();
    $found = app(CustomerRepositoryInterface::class)->findMany($ids);

    expect($found)->toHaveCount(5)
        ->and(DB::getQueryLog())->toHaveCount(1)
        ->and($found->first())->toBeInstanceOf(CustomerData::class);
});

test('the directory reports an unknown customer as absent, not as an error', function () {
    expect(app(CustomerRepositoryInterface::class)->find(9999))->toBeNull();
});

test('adding a customer announces data a listener cannot write back through', function () {
    $seen = null;

    Event::listen(CustomerAdded::class, function (CustomerAdded $event) use (&$seen) {
        $seen = $event->customer;
    });

    app(CreateCustomer::class)->handle(['name' => 'Announced']);

    expect($seen)->toBeInstanceOf(CustomerData::class)
        ->and($seen->name)->toBe('Announced');
});

test('an announcement is held until the outer transaction commits', function () {
    $heard = [];

    Event::listen(CustomerAdded::class, function (CustomerAdded $event) use (&$heard) {
        $heard[] = $event->customer->name;
    });

    DB::transaction(function () use (&$heard) {
        app(CreateCustomer::class)->handle(['name' => 'Part of something larger']);

        expect($heard)->toBe([]);
    });

    expect($heard)->toBe(['Part of something larger']);
});

test('an announcement from a transaction that rolls back is never heard', function () {
    // The case this guards: Sales saving a sale and its ledger entry together,
    // and the sale failing. The customer was never added, and the entry never
    // posted — so nobody may be told they were.
    $heard = [];

    Event::listen(CustomerAdded::class, function () use (&$heard) {
        $heard[] = CustomerAdded::class;
    });
    Event::listen(AccountEntryPosted::class, function () use (&$heard) {
        $heard[] = AccountEntryPosted::class;
    });

    $postedBy = AdminUser::factory()->create()->getKey();

    try {
        DB::transaction(function () use ($postedBy) {
            $customer = app(CreateCustomer::class)->handle(['name' => 'Rolled back']);

            app(AccountLedgerInterface::class)->post(
                $customer->id,
                TransactionType::Debit,
                Money::fromDecimalString('10.00'),
                $postedBy,
            );

            throw new RuntimeException('The larger operation failed.');
        });
    } catch (RuntimeException) {
        //
    }

    expect($heard)->toBe([])
        ->and(Customer::query()->where('name', 'Rolled back')->exists())->toBeFalse();
});

test('another module moves a balance through the contract, not the action', function () {
    $customer = app(CreateCustomer::class)->handle(['name' => 'On account']);
    $postedBy = AdminUser::factory()->create()->getKey();

    $ledger = app(AccountLedgerInterface::class);

    $ledger->post(
        $customer->id,
        TransactionType::Debit,
        Money::fromDecimalString('40.00'),
        $postedBy,
        'Two bags of rice',
    );

    expect(app(AccountRepositoryInterface::class)->findByCustomer($customer->id)->outstanding->toDecimalString())->toBe('40.00');
});

test('the ledger opens an account the announcement never did', function () {
    // The listener failed, or the customer predates it. Either way the first
    // posting must not become an error.
    Event::fake([CustomerAdded::class]);

    $customer = app(CreateCustomer::class)->handle(['name' => 'No account yet']);

    expect(CustomerAccount::query()->where('customer_id', $customer->id)->exists())->toBeFalse();

    app(AccountLedgerInterface::class)->post(
        $customer->id,
        TransactionType::Debit,
        Money::fromDecimalString('5.00'),
        AdminUser::factory()->create()->getKey(),
    );

    expect(app(AccountRepositoryInterface::class)->findByCustomer($customer->id)->outstanding->toDecimalString())->toBe('5.00');
});

test('the repository reports an account that does not exist as absent', function () {
    // It fetches what is stored. What an absent account means is the caller's
    // decision, not a default the repository invents.
    expect(app(AccountRepositoryInterface::class)->findByCustomer(9999))->toBeNull();
});

test('account standing answers the credit question without exposing the ledger', function () {
    $customer = app(CreateCustomer::class)->handle(['name' => 'Limited']);

    CustomerAccount::query()
        ->where('customer_id', $customer->id)
        ->update(['credit_limit' => Money::fromDecimalString('50.00')->minorUnits]);

    $standing = app(AccountRepositoryInterface::class)->findByCustomer($customer->id);

    expect($standing->isInDebt())->toBeFalse()
        ->and($standing->wouldBreachLimit(Money::fromDecimalString('50.01')))->toBeTrue()
        ->and($standing->wouldBreachLimit(Money::fromDecimalString('50.00')))->toBeFalse()
        // Reading where an account stands must not come with the ability to move it.
        ->and(method_exists($standing, 'post'))->toBeFalse();
});

test('a refusal crosses the boundary as data, not as the account', function () {
    $customer = app(CreateCustomer::class)->handle(['name' => 'At the limit']);

    CustomerAccount::query()
        ->where('customer_id', $customer->id)
        ->update(['credit_limit' => Money::fromDecimalString('10.00')->minorUnits]);

    try {
        app(AccountLedgerInterface::class)->post(
            $customer->id,
            TransactionType::Debit,
            Money::fromDecimalString('10.01'),
            AdminUser::factory()->create()->getKey(),
        );

        $this->fail('The debit should have been refused.');
    } catch (CreditLimitExceeded $refusal) {
        expect($refusal->customerId)->toBe($customer->id)
            ->and($refusal->creditLimit->toDecimalString())->toBe('10.00')
            ->and($refusal->attemptedOutstanding->toDecimalString())->toBe('10.01');
    }
});

test('opening an account still happens by announcement, not by a call', function () {
    // Customers does not know Accounts exists; the account appears anyway.
    $customer = app(CreateCustomer::class)->handle(['name' => 'Announced only']);

    expect(CustomerAccount::query()->where('customer_id', $customer->id)->exists())->toBeTrue();
});

test('the account repository reads many accounts in one query', function () {
    // A screen listing customers with what each owes must not issue a query
    // per row. An id with no account is simply absent.
    $opened = collect(range(1, 4))
        ->map(fn (int $i) => app(CreateCustomer::class)->handle(['name' => "Customer {$i}"])->id)
        ->all();

    DB::enableQueryLog();
    $accounts = app(AccountRepositoryInterface::class)->findManyByCustomers([...$opened, 9999]);

    expect(DB::getQueryLog())->toHaveCount(1)
        ->and($accounts->keys()->sort()->values()->all())->toBe($opened)
        ->and($accounts->has(9999))->toBeFalse();
});

test('reads and the one write are separate contracts', function () {
    // The repository only reads; moving a balance has rules, so it is the
    // ledger's alone.
    $repository = new ReflectionClass(AccountRepositoryInterface::class);
    $ledger = new ReflectionClass(AccountLedgerInterface::class);

    expect(collect($repository->getMethods())->map->getName()->all())->toBe(['findByCustomer', 'findManyByCustomers', 'save'])
        ->and(collect($ledger->getMethods())->map->getName()->all())->toBe(['post']);
});

test('the customer repository saves and deletes, and fetches what it saved', function () {
    $repository = app(CustomerRepositoryInterface::class);

    $saved = $repository->save(new CustomerData(id: null, name: 'Stored', phone: '01000000009'));

    expect($saved->id)->toBeInt()
        ->and($repository->find($saved->id)?->name)->toBe('Stored');

    $renamed = $repository->save(new CustomerData(id: $saved->id, name: 'Renamed', phone: $saved->phone));

    expect($renamed->id)->toBe($saved->id)
        ->and($repository->find($saved->id)?->name)->toBe('Renamed');

    $repository->delete($saved->id);

    expect($repository->find($saved->id))->toBeNull();
});

test('a customer with a ledger cannot be deleted from under it', function () {
    // Accounts depends on Customers, and its foreign key is what says so to
    // the database: the customer row cannot go while their history stands.
    $customer = app(CreateCustomer::class)->handle(['name' => 'Has a tab']);

    expect(fn () => app(CustomerRepositoryInterface::class)->delete($customer->id))
        ->toThrow(QueryException::class);
});

test('saving an account stores its settings and never what it owes', function () {
    // What the customer owes is the ledger's total. A save that wrote it would
    // bypass the row lock and the credit limit — the hole the ledger closes.
    $customer = app(CreateCustomer::class)->handle(['name' => 'Settings only']);
    $repository = app(AccountRepositoryInterface::class);

    $saved = $repository->save(new AccountStanding(
        customerId: $customer->id,
        outstanding: Money::fromDecimalString('999.00'),
        creditLimit: Money::fromDecimalString('150.00'),
    ));

    expect($saved->creditLimit->toDecimalString())->toBe('150.00')
        ->and($saved->outstanding->isZero())->toBeTrue()
        ->and($repository->findByCustomer($customer->id)?->outstanding->isZero())->toBeTrue();
});
