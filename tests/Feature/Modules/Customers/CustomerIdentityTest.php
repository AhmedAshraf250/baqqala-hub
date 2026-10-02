<?php

use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\FrontendUser;
use App\Modules\Accounts\Contracts\AccountRepositoryInterface;
use App\Modules\Accounts\Database\Models\CustomerAccount;
use App\Modules\Customers\Contracts\CustomerRepositoryInterface;
use App\Modules\Customers\Contracts\Data\CustomerData;
use App\Modules\Customers\Database\Models\Customer;
use App\Modules\Customers\Domain\Actions\CreateCustomer;
use App\Modules\Customers\Domain\Actions\GrantPortalAccess;
use App\Modules\Customers\Domain\Exceptions\CustomerAlreadyHasLogin;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(LazilyRefreshDatabase::class);

test('the shop can add a customer who has no login at all', function () {
    $customer = app(CreateCustomer::class)->handle([
        'name' => 'أم أحمد',
        'phone' => '01000000001',
    ]);

    expect($customer->userId)->toBeNull()
        ->and($customer->hasPortalAccess())->toBeFalse()
        // The tab exists from day one, so no screen has to handle its absence.
        ->and(CustomerAccount::query()->where('customer_id', $customer->id)->exists())->toBeTrue()
        ->and(app(AccountRepositoryInterface::class)->findByCustomer($customer->id)?->outstanding->isZero())->toBeTrue();
});

test('there is no public sign-up', function (string $path) {
    // A customer is someone the shop already deals with. A public form would
    // create a second, unlinked identity for a person already in the books.
    $this->get($path)->assertNotFound();
})->with(['/register', '/account/register', '/admin/register']);

test('the register route does not exist', function () {
    expect(Route::has('register'))->toBeFalse()
        ->and(Route::has('register.store'))->toBeFalse();
});

test('an existing customer can be handed a login without losing their history', function () {
    $customer = app(CreateCustomer::class)->handle([
        'name' => 'عم سيد',
        'phone' => '01000000002',
    ]);

    $accountId = CustomerAccount::query()->where('customer_id', $customer->id)->value('id');

    $user = app(GrantPortalAccess::class)->handle($customer->id, 'sayed@example.test', 'a-good-password');

    $customer = app(CustomerRepositoryInterface::class)->find($customer->id);

    expect($user->area)->toBe(Area::Frontend)
        ->and($customer->userId)->toBe($user->getKey())
        // Same account, same ledger: the tab follows them into the portal.
        ->and(CustomerAccount::query()->where('customer_id', $customer->id)->value('id'))->toBe($accountId);
});

test('a customer cannot be given two logins', function () {
    $customer = app(CreateCustomer::class)->handle(['name' => 'Twice']);

    app(GrantPortalAccess::class)->handle($customer->id, 'first@example.test', 'a-good-password');

    expect(fn () => app(GrantPortalAccess::class)->handle($customer->id, 'second@example.test', 'a-good-password'))
        ->toThrow(CustomerAlreadyHasLogin::class);
});

test('a login belongs to at most one customer', function () {
    $user = FrontendUser::factory()->create();

    app(CreateCustomer::class)->handle(['name' => 'First'], login: $user);

    expect(fn () => app(CreateCustomer::class)->handle(['name' => 'Second'], login: $user))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('two grants racing for one customer leave no orphaned login', function () {
    // The race: two people grant the same customer access at once. Both see
    // the customer unlinked, both create a login, and the later link used to
    // overwrite the earlier one — leaving a login that belonged to no one.
    // Here the other grant lands in exactly that window: after this one has
    // checked, before it links.
    $customer = app(CreateCustomer::class)->handle(['name' => 'Raced']);
    $firstGrant = FrontendUser::factory()->create();

    FrontendUser::created(function (FrontendUser $login) use ($customer, $firstGrant): void {
        if ($login->email === 'second@example.test') {
            Customer::query()->whereKey($customer->id)->update(['user_id' => $firstGrant->getKey()]);
        }
    });

    expect(fn () => app(GrantPortalAccess::class)->handle($customer->id, 'second@example.test', 'a-good-password'))
        ->toThrow(CustomerAlreadyHasLogin::class);

    // The refused grant's login went with its transaction.
    expect(FrontendUser::query()->where('email', 'second@example.test')->exists())->toBeFalse();
});

test('a customer can only be linked to a frontend login', function () {
    // The type says it: an administrator's login cannot become a customer.
    $parameter = (new ReflectionMethod(CreateCustomer::class, 'handle'))->getParameters()[1];

    expect((string) $parameter->getType())->toBe('?'.FrontendUser::class);
});

test('a customer added with a login is linked to it', function () {
    $login = FrontendUser::factory()->create();

    $customer = app(CreateCustomer::class)->handle(['name' => 'أبو علي'], $login);

    expect($customer->userId)->toBe($login->getKey())
        ->and(Customer::query()->find($customer->id)?->user_id)->toBe($login->getKey());
});

test('saving a customer through the repository never links or unlinks a login', function () {
    // The repository is what other modules hold. Through it, any of them
    // could once attach an administrator's login to a customer, or detach a
    // customer's own — the rules GrantPortalAccess keeps, skipped.
    $customers = app(CustomerRepositoryInterface::class);
    $linked = app(CreateCustomer::class)->handle(['name' => 'أم أحمد'], FrontendUser::factory()->create());
    $unlinked = app(CreateCustomer::class)->handle(['name' => 'أبو علي']);
    $stranger = FrontendUser::factory()->create();

    $customers->save(new CustomerData($linked->id, $linked->name, null, null, null, userId: null));
    $customers->save(new CustomerData($unlinked->id, $unlinked->name, null, null, null, userId: $stranger->getKey()));
    $created = $customers->save(new CustomerData(null, 'جديد', null, null, null, userId: $stranger->getKey()));

    expect($customers->find($linked->id)?->userId)->toBe($linked->userId)
        ->and($customers->find($unlinked->id)?->userId)->toBeNull()
        ->and($created->userId)->toBeNull();
});
