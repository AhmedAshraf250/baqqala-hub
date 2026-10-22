<?php

use App\Modules\Customers\Contracts\CustomerDirectoryInterface;
use App\Modules\Customers\Contracts\Data\CustomerData;
use App\Modules\Customers\Contracts\Events\CustomerAdded;
use App\Modules\Customers\Contracts\Exceptions\PhoneAlreadyUsed;
use App\Modules\Customers\Database\Models\Customer;
use App\Modules\Customers\Domain\Actions\CreateCustomer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->directory = app(CustomerDirectoryInterface::class);
});

test('the counter adds a customer, with the phone as its digits, and it is announced', function () {
    Event::fake([CustomerAdded::class]);

    $added = $this->directory->add(new CustomerData(null, 'أم كريم', phone: '٠١٠٠ ١٢٣ ٤٥٦٧'));

    expect($added->id)->not->toBeNull()
        ->and($added->phone)->toBe('01001234567')
        ->and($added->hasPortalAccess())->toBeFalse();

    Event::assertDispatched(CustomerAdded::class, fn (CustomerAdded $event) => $event->customer->id === $added->id);
});

test('a phone another customer has is refused, however it is typed, with who has it', function (string $typed) {
    $holder = $this->directory->add(new CustomerData(null, 'أم كريم', phone: '01001234567'));

    try {
        $this->directory->add(new CustomerData(null, 'Someone else', phone: $typed));
        $this->fail('The second customer was added under the same phone.');
    } catch (PhoneAlreadyUsed $refusal) {
        expect($refusal->heldByCustomerId)->toBe($holder->id)
            ->and($refusal->phone)->toBe('01001234567');
    }

    expect(Customer::query()->where('name', 'Someone else')->exists())->toBeFalse();
})->with([
    'the same' => ['01001234567'],
    'Arabic-Indic, grouped' => ['٠١٠٠ ١٢٣ ٤٥٦٧'],
    'with dashes' => ['0100-123-4567'],
]);

test('customers without a phone are not one another\'s duplicates', function () {
    $this->directory->add(new CustomerData(null, 'First'));
    $this->directory->add(new CustomerData(null, 'Second', phone: '  '));

    expect(Customer::query()->whereNull('phone')->count())->toBe(2);
});

test('adding through the action is under the same rule', function () {
    Customer::factory()->create(['phone' => '01001234567']);

    expect(fn () => app(CreateCustomer::class)->handle(['name' => 'Again', 'phone' => '0100 123 4567']))
        ->toThrow(PhoneAlreadyUsed::class);
});

test('a customer is updated, keeping their own phone and refusing someone else\'s', function () {
    $mona = $this->directory->add(new CustomerData(null, 'منى', phone: '01001234567'));
    $taken = $this->directory->add(new CustomerData(null, 'Taken', phone: '01119876543'));

    $moved = $this->directory->update(new CustomerData($mona->id, 'منى عادل', phone: '٠١٠٠١٢٣٤٥٦٧', address: 'الدور التاني'));

    expect($moved->name)->toBe('منى عادل')
        ->and($moved->phone)->toBe('01001234567')
        ->and($moved->address)->toBe('الدور التاني')
        ->and(Customer::query()->findOrFail($mona->id)->search_name)->toBe('مني عادل');

    expect(fn () => $this->directory->update(new CustomerData($mona->id, 'منى عادل', phone: $taken->phone)))
        ->toThrow(PhoneAlreadyUsed::class);
});

test('an update leaves the customer\'s login as it is', function () {
    $customer = Customer::factory()->withLogin()->create();

    $updated = $this->directory->update(new CustomerData($customer->id, 'Renamed', phone: $customer->phone));

    expect($updated->userId)->toBe($customer->user_id);
});

test('the directory refuses what is not its step', function (CustomerData $customer, string $method) {
    expect(fn () => $this->directory->{$method}($customer))->toThrow(LogicException::class);
})->with([
    'adding someone already stored' => [new CustomerData(5, 'Stored'), 'add'],
    'adding someone with a login' => [new CustomerData(null, 'With a login', userId: 5), 'add'],
    'updating someone not stored' => [new CustomerData(null, 'Not stored'), 'update'],
]);
