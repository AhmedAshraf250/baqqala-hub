<?php

use App\Admin\Authorization\PermissionRegistry;
use App\Admin\Authorization\SyncPermissions;
use App\Foundation\Area\Area;
use App\Foundation\Contracts\Modules\SyncStepInterface;
use App\Foundation\Identity\Models\AdminUser;
use App\Foundation\Modules\ModuleInspector;
use App\Foundation\Modules\ModuleRegistry;
use App\Foundation\Modules\ModuleServiceProvider;
use App\Foundation\Modules\Sync\SyncCaches;
use App\Foundation\Money\Money;
use App\Modules\Accounts\AccountsServiceProvider;
use App\Modules\Accounts\Contracts\AccountLedgerInterface;
use App\Modules\Accounts\Contracts\Data\ChargeLine;
use App\Modules\Accounts\Contracts\Data\ChargeRequest;
use App\Modules\Catalog\CatalogServiceProvider;
use App\Modules\Customers\Database\Models\Customer;
use App\Modules\Purchases\PurchasesServiceProvider;
use App\Modules\Sales\SalesServiceProvider;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->seed(AuthorizationSeeder::class);

    // The caches step reads and clears the compiled views. Pointed at the
    // working tree's, its answer depended on what the developer last opened,
    // and its fix deleted what every later test in the run was reading.
    $this->compiledViews = sys_get_temp_dir().'/compiled-views-'.uniqid();
    File::ensureDirectoryExists($this->compiledViews);
    config(['view.compiled' => $this->compiledViews]);
    app()->forgetInstance('livewire.compiler');
});

afterEach(function () {
    File::deleteDirectory($this->compiledViews);
});

/**
 * @param  array<string, mixed>  $parameters
 */
function syncReport(array $parameters = []): string
{
    // Without it, the question nobody answers reads the console — and on
    // Windows that switches its code page for the rest of the run, after which
    // no `⚡` file name resolves.
    Artisan::call('app:sync', [...$parameters, '--no-interaction' => true]);

    return Artisan::output();
}

test('a freshly seeded installation is in sync', function () {
    expect(syncReport())->toContain('Everything stored matches the code.');
});

test('a permission in the code but not in the database refuses everyone but the owner, and the report says why', function () {
    // The case the command is for: a module gained a permission, and nobody
    // stored it. Nothing fails loudly — the screen just refuses.
    Permission::findByName('accounts.view', Area::Admin->guard())->delete();

    $manager = AdminUser::factory()->create();
    $manager->assignRole('manager');
    $this->actingAs($manager, Area::Admin->guard())->get(route('admin.accounts.index'))->assertForbidden();

    actingAsAdmin();
    $this->get(route('admin.accounts.index'))->assertOk();

    expect(syncReport())
        ->toMatch('/accounts\.view\W+in the code, not in the database/')
        ->toMatch('/manager\W+should have accounts\.view/');
});

test('a permission left in the database by a rename is reported', function () {
    Permission::create(['name' => 'customers.retired', 'guard_name' => Area::Admin->guard()]);

    expect(syncReport())->toMatch('/customers\.retired\W+in the database, no longer in the code/');
});

test('a role holding more than config/roles.php gives it is reported', function () {
    Role::findByName('cashier', Area::Admin->guard())->givePermissionTo('customers.manage');

    expect(syncReport())->toMatch('/cashier\W+has customers\.manage, which config\/roles\.php does not give it/');
});

test('a migration not run is reported', function () {
    DB::table('migrations')->where('migration', 'like', '%_create_customers_table')->delete();

    expect(syncReport())->toMatch('/customers · \S+_create_customers_table\W+not run yet/');
});

test('asked about one module, it reports that module only', function () {
    Permission::create(['name' => 'customers.retired', 'guard_name' => Area::Admin->guard()]);
    Permission::create(['name' => 'accounts.retired', 'guard_name' => Area::Admin->guard()]);

    expect(syncReport(['module' => 'accounts']))
        ->toContain('accounts.retired')
        ->not->toContain('customers.retired');
});

test('without an answer it changes nothing', function () {
    // Run from a script with no one to answer, the question defaults to no.
    Permission::findByName('customers.view', Area::Admin->guard())->delete();

    expect(syncReport())->toContain('Nothing was changed.')
        ->and(Permission::query()->where('name', 'customers.view')->exists())->toBeFalse();
});

test('it asks before changing anything, and a no leaves everything as it was', function () {
    Permission::findByName('customers.view', Area::Admin->guard())->delete();

    // One permission, and the three roles that should hold it.
    $this->artisan('app:sync')
        ->expectsConfirmation('Fix these 4 now?', 'no')
        ->expectsOutputToContain('Nothing was changed.')
        ->assertSuccessful();

    expect(Permission::query()->where('name', 'customers.view')->exists())->toBeFalse();
});

test('approved, it stores the permission and gives it to the roles the file gives it', function () {
    Permission::findByName('accounts.view', Area::Admin->guard())->delete();

    $this->artisan('app:sync')
        ->expectsConfirmation('Fix these 4 now?', 'yes')
        ->expectsOutputToContain('Everything stored now matches the code.')
        ->assertSuccessful();

    $manager = AdminUser::factory()->create();
    $manager->assignRole('manager');
    $this->actingAs($manager, Area::Admin->guard())->get(route('admin.accounts.index'))->assertOk();
});

test('it removes a retired permission and a grant the file does not give', function () {
    Permission::create(['name' => 'customers.retired', 'guard_name' => Area::Admin->guard()]);
    Role::findByName('cashier', Area::Admin->guard())->givePermissionTo('customers.manage');

    $this->artisan('app:sync', ['--force' => true])->assertSuccessful();

    expect(Permission::query()->where('name', 'customers.retired')->exists())->toBeFalse()
        ->and(Role::findByName('cashier', Area::Admin->guard())->fresh()->hasPermissionTo('customers.manage'))->toBeFalse()
        ->and(Role::findByName('cashier', Area::Admin->guard())->fresh()->hasPermissionTo('customers.view'))->toBeTrue();
});

test('it runs the migrations that have not run', function () {
    app(Migrator::class)->reset([app_path('Modules/Catalog/Database/Migrations')]);

    expect(Schema::hasTable('products'))->toBeFalse();

    $this->artisan('app:sync', ['--force' => true])->assertSuccessful();

    expect(Schema::hasTable('products'))->toBeTrue();
});

test('a module\'s step waits for the migrations that add what it reads, rather than fail', function () {
    // An installation updated to new code: an account with entries from
    // before, and the columns the balance check reads still to be added by
    // migrations only app:sync runs. Its report used to stop on the first one.
    $customer = Customer::factory()->create();
    app(AccountLedgerInterface::class)->charge(new ChargeRequest(
        $customer->getKey(),
        AdminUser::factory()->create()->getKey(),
        (string) Str::uuid(),
        [ChargeLine::free('حاجات', Money::fromDecimalString('120.00'))],
    ));

    foreach (['create_account_transaction_items_table', 'add_ledger_summary_to_customer_accounts_table', 'add_kinds_and_links_to_account_transactions_table'] as $name) {
        $file = collect(File::glob(app_path("Modules/Accounts/Database/Migrations/*_{$name}.php")))->sole();
        (require $file)->down();
        DB::table('migrations')->where('migration', basename($file, '.php'))->delete();
    }

    expect(Schema::hasColumn('customer_accounts', 'last_entry_id'))->toBeFalse()
        ->and(syncReport())->toMatch('/accounts · \S+_add_ledger_summary_to_customer_accounts_table\W+not run yet/');

    $this->artisan('app:sync', ['--force' => true])
        ->expectsOutputToContain('Everything stored now matches the code.')
        ->assertSuccessful();

    expect(DB::table('customer_accounts')->where('customer_id', $customer->getKey())->value('last_entry_id'))->not->toBeNull();
});

test('fixing one module leaves the others as they are', function () {
    Permission::create(['name' => 'customers.retired', 'guard_name' => Area::Admin->guard()]);
    Permission::create(['name' => 'accounts.retired', 'guard_name' => Area::Admin->guard()]);

    $this->artisan('app:sync', ['module' => 'accounts', '--force' => true])->assertSuccessful();

    expect(Permission::query()->where('name', 'accounts.retired')->exists())->toBeFalse()
        ->and(Permission::query()->where('name', 'customers.retired')->exists())->toBeTrue();
});

test('the seeder and the sync command agree, because they are the same steps', function () {
    // Seeding is fixing without asking. After it, the report has nothing to say.
    Permission::findByName('customers.view', Area::Admin->guard())->delete();
    Permission::create(['name' => 'customers.retired', 'guard_name' => Area::Admin->guard()]);

    $this->seed(AuthorizationSeeder::class);

    expect(syncReport())->toContain('Everything stored matches the code.');
});

test('what a disabled module stored is not out of sync: it stays by design', function () {
    // Checked against the permissions the three would stop defining once
    // disabled — the registry a request builds without them.
    config(['modules.enabled' => array_values(array_diff(config('modules.enabled'), [
        CatalogServiceProvider::class, AccountsServiceProvider::class, PurchasesServiceProvider::class, SalesServiceProvider::class,
    ]))]);
    $running = app(ModuleRegistry::class)->all()->except(['catalog', 'accounts', 'purchases', 'sales'])->values()->all();

    $check = new SyncPermissions(new PermissionRegistry(new ModuleRegistry($running)), app(ModuleInspector::class), app(PermissionRegistrar::class));

    expect($check->findings())->toBe([]);
});

test('compiled views older than a component class are reported', function () {
    // A compiled view remembers whether `<x-admin::…>` had a class when it was
    // compiled; this is how a new class once rendered its template without
    // its data until `view:clear`.
    $compiled = sys_get_temp_dir().'/compiled-views-'.uniqid();
    File::ensureDirectoryExists($compiled);
    touch($compiled.'/old.php', strtotime('2000-01-01'));
    config(['view.compiled' => $compiled]);

    try {
        expect(syncReport())->toMatch('/views\W+1 compiled before a component class last changed/');
    } finally {
        File::deleteDirectory($compiled);
    }
});

test('a compiled view the operating system will not delete is said out loud, not reported fixed', function () {
    touch($this->compiledViews.'/old.php', strtotime('2000-01-01'));

    // view:clear runs and reports success, and the file is still there —
    // what Windows does to a file an administrator's terminal compiled. The
    // step used to answer DONE, and the report after it "still out of sync",
    // with nothing to say why.
    $artisan = Mockery::mock(ConsoleKernel::class);
    $artisan->shouldReceive('call')->with('view:clear')->once()->andReturn(0);

    $step = new SyncCaches(app(), config(), $artisan);

    expect(fn () => $step->fix())->toThrow(RuntimeException::class, 'Could not delete 1 compiled view');
});

test('a step that cannot fix says why, and the rest still run', function () {
    Permission::findByName('customers.view', Area::Admin->guard())->delete();

    app()->instance('sync.refusing', new class implements SyncStepInterface
    {
        public function title(): string
        {
            return 'Refusing';
        }

        public function findings(?ModuleServiceProvider $module = null): array
        {
            return [['something', 'out of sync']];
        }

        public function fix(?ModuleServiceProvider $module = null): void
        {
            throw new RuntimeException('The disk said no.');
        }
    });
    app()->tag(['sync.refusing'], SyncStepInterface::class);

    $exit = Artisan::call('app:sync', ['--force' => true, '--no-interaction' => true]);
    $output = Artisan::output();

    expect($exit)->toBe(1)
        ->and($output)->toContain('FAIL')
        ->and($output)->toMatch('/Refusing\W+[\d.]+ms FAIL/')
        ->and($output)->toContain('The disk said no')
        ->and(Permission::query()->where('name', 'customers.view')->exists())->toBeTrue();
});

test('a route cache built before the routes changed is reported', function () {
    // Relative to the project, for the reason given in RenderCostTest.
    $relative = 'storage/framework/routes-cache-'.uniqid().'.php';
    $cache = base_path($relative);
    file_put_contents($cache, '<?php return [];');
    touch($cache, strtotime('2000-01-01'));

    // Laravel decides once, at boot, whether routes are cached, and keeps the
    // answer; the path is read when asked.
    $_SERVER['APP_ROUTES_CACHE'] = $relative;
    app()->instance('routes.cached', true);

    try {
        expect(syncReport())->toMatch('/routes\W+cached before the latest change to its files/');
    } finally {
        unset($_SERVER['APP_ROUTES_CACHE']);
        app()->instance('routes.cached', false);
        unlink($cache);
    }
});

test('a module that does not run is refused by name', function () {
    $this->artisan('app:sync', ['module' => 'nope'])
        ->expectsOutputToContain('[nope] is not a running module')
        ->assertFailed();
});
