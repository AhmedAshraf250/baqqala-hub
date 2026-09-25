<?php

use App\Foundation\Identity\Models\AdminUser;
use App\Foundation\Identity\Models\FrontendUser;
use App\Foundation\Identity\Models\User;
use App\Modules\Accounts\AccountsServiceProvider;
use App\Modules\Catalog\CatalogServiceProvider;
use App\Modules\Catalog\Database\Models\Product;
use App\Modules\Customers\CustomersServiceProvider;
use App\Modules\Customers\Database\Models\Customer;
use App\Modules\Customers\Domain\Actions\GrantPortalAccess;
use App\Modules\Purchases\PurchasesServiceProvider;
use App\Modules\Sales\SalesServiceProvider;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Fixtures\Modules\Probe\ProbeServiceProvider;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->seed(AuthorizationSeeder::class);
});

/**
 * Take modules out of config/modules.php, the way an installation disables
 * them.
 */
function disable(string ...$modules): void
{
    config(['modules.enabled' => array_values(array_diff(config('modules.enabled'), $modules))]);
}

/**
 * Uninstall modules that are in the way — the ones depending on the module a
 * test is about — the way a developer would, in order.
 */
function uninstalled(string ...$keys): void
{
    foreach ($keys as $key) {
        test()->artisan('app:module:uninstall', ['module' => $key, '--force' => true])->assertSuccessful();
    }
}

/**
 * The catalog, disabled with the two modules that depend on it, and those two
 * uninstalled: what a test about uninstalling the catalog starts from.
 */
function catalogReadyToUninstall(): void
{
    disable(CatalogServiceProvider::class, PurchasesServiceProvider::class, SalesServiceProvider::class);
    uninstalled('purchases', 'sales');
}

test('an enabled module is refused, and nothing is touched', function () {
    $this->artisan('app:module:uninstall', ['module' => 'catalog', '--force' => true])->assertFailed();

    expect(Schema::hasTable('products'))->toBeTrue();
});

test('a module is refused while one that depends on it still has its tables', function () {
    // Accounts is disabled but its tables are still there, pointing at the
    // customers table: Customers cannot go first.
    disable(CustomersServiceProvider::class, AccountsServiceProvider::class, SalesServiceProvider::class);

    $this->artisan('app:module:uninstall', ['module' => 'customers', '--force' => true])
        ->expectsOutputToContain('[accounts]')
        ->assertFailed();

    expect(Schema::hasTable('customers'))->toBeTrue();
});

test('a module is refused while one that depends on it is installed, even with no tables of its own', function () {
    // Purchases has no tables, but its permissions are still stored: it is
    // installed, and could be enabled again expecting the catalog.
    disable(CatalogServiceProvider::class, PurchasesServiceProvider::class, SalesServiceProvider::class);

    $this->artisan('app:module:uninstall', ['module' => 'catalog', '--force' => true])
        ->expectsOutputToContain('Uninstall [purchases] first')
        ->assertFailed();

    expect(Schema::hasTable('products'))->toBeTrue();
});

test('a dry run says what would go, and removes nothing', function () {
    catalogReadyToUninstall();
    Product::factory()->count(3)->create();

    $this->artisan('app:module:uninstall', ['module' => 'catalog', '--dry-run' => true])
        ->expectsOutputToContain('table products')
        ->assertSuccessful();

    expect(Schema::hasTable('products'))->toBeTrue()
        ->and(Product::query()->count())->toBe(3);
});

test('it asks for the module\'s key, and stops on anything else', function () {
    catalogReadyToUninstall();

    $this->artisan('app:module:uninstall', ['module' => 'catalog'])
        ->expectsQuestion('Type [catalog] to remove all of this', 'yes')
        ->assertFailed();

    expect(Schema::hasTable('products'))->toBeTrue();
});

test('uninstalling removes its tables, migrations, and permissions, and nothing else', function () {
    catalogReadyToUninstall();
    Product::factory()->count(2)->create();

    expect(Role::findByName('manager', 'admin')->hasPermissionTo('catalog.view'))->toBeTrue();

    $this->artisan('app:module:uninstall', ['module' => 'catalog', '--force' => true])
        ->expectsOutputToContain('Nothing of [catalog] is left')
        ->assertSuccessful();

    expect(Schema::hasTable('products'))->toBeFalse()
        ->and(Schema::hasTable('categories'))->toBeFalse()
        ->and(DB::table('migrations')->where('migration', 'like', '%_create_products_table')->exists())->toBeFalse()
        ->and(Permission::query()->where('name', 'like', 'catalog.%')->exists())->toBeFalse()
        ->and(Role::findByName('manager', 'admin')->permissions()->where('name', 'like', 'catalog.%')->exists())->toBeFalse();

    // Everything that is not the catalog's is untouched.
    expect(Schema::hasTable('customers'))->toBeTrue()
        ->and(Schema::hasTable('customer_accounts'))->toBeTrue()
        ->and(Permission::query()->where('name', 'customers.view')->exists())->toBeTrue();
});

test('a module\'s own leftovers go first, while its tables can still be read', function () {
    // The case `UninstallableInterface` is for: files the module stored,
    // which no migration knows about.
    Storage::fake('local');
    Storage::disk('local')->put('probe/receipt.pdf', 'a receipt');
    $this->artisan('migrate', ['--path' => 'tests/Fixtures/Modules/Probe/Database/Migrations', '--force' => true]);

    ProbeServiceProvider::$tableExistedDuringUninstall = null;

    $this->artisan('app:module:uninstall', ['module' => ProbeServiceProvider::class, '--force' => true])
        ->expectsOutputToContain('probe/receipt.pdf')
        ->assertSuccessful();

    expect(ProbeServiceProvider::$tableExistedDuringUninstall)->toBeTrue()
        ->and(Storage::disk('local')->exists('probe/receipt.pdf'))->toBeFalse()
        ->and(Schema::hasTable('probe_items'))->toBeFalse();
});

test('a module whose own cleanup leaves something is stopped before its tables go', function () {
    // Its tables are what its cleanup reads to find what to delete. Dropping
    // them after a cleanup that failed would leave the files with no way
    // back to them.
    Storage::fake('local');
    Storage::disk('local')->put('probe/receipt.pdf', 'a receipt');
    $this->artisan('migrate', ['--path' => 'tests/Fixtures/Modules/Probe/Database/Migrations', '--force' => true]);

    ProbeServiceProvider::$forgetsItsFiles = true;

    try {
        $this->artisan('app:module:uninstall', ['module' => ProbeServiceProvider::class, '--force' => true])
            ->expectsOutputToContain('own uninstall() left: stored file probe/receipt.pdf')
            ->assertFailed();
    } finally {
        ProbeServiceProvider::$forgetsItsFiles = false;
    }

    expect(Schema::hasTable('probe_items'))->toBeTrue();
});

test('uninstalling customers removes the portal logins it gave out, and no other login', function () {
    // The real case for `UninstallableInterface`: the logins are rows of
    // `users`, which no migration of the module drops.
    disable(CustomersServiceProvider::class, AccountsServiceProvider::class, SalesServiceProvider::class);

    $customer = Customer::factory()->create();
    app(GrantPortalAccess::class)->handle($customer->id, 'umm.ahmed@example.test', 'a-long-password');
    $admin = AdminUser::factory()->create();
    $loginNoCustomerHolds = FrontendUser::factory()->create();

    uninstalled('accounts', 'sales');

    $this->artisan('app:module:uninstall', ['module' => 'customers', '--force' => true])
        ->expectsOutputToContain('1 portal login given to customers')
        ->assertSuccessful();

    expect(User::query()->where('email', 'umm.ahmed@example.test')->exists())->toBeFalse()
        ->and(User::query()->whereKey($admin->getKey())->exists())->toBeTrue()
        ->and(User::query()->whereKey($loginNoCustomerHolds->getKey())->exists())->toBeTrue()
        ->and(Schema::hasTable('customers'))->toBeFalse();
});

test('a module with nothing left says so', function () {
    catalogReadyToUninstall();

    $this->artisan('app:module:uninstall', ['module' => 'catalog', '--force' => true])->assertSuccessful();

    $this->artisan('app:module:uninstall', ['module' => 'catalog', '--force' => true])
        ->expectsOutputToContain('Nothing of [catalog] is left')
        ->assertSuccessful();
});
