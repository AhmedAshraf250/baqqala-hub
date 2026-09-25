<?php

use App\Admin\Authorization\PermissionRegistry;
use App\Admin\Authorization\SyncPermissions;
use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\AdminUser;
use App\Foundation\Modules\ModuleInspector;
use App\Foundation\Modules\ModuleRegistry;
use App\Modules\Catalog\CatalogServiceProvider;
use App\Modules\Purchases\PurchasesServiceProvider;
use App\Modules\Sales\SalesServiceProvider;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->seed(AuthorizationSeeder::class);
});

/**
 * @param  array<string, mixed>  $parameters
 */
function syncReport(array $parameters = []): string
{
    Artisan::call('app:sync', $parameters);

    return Artisan::output();
}

test('a freshly seeded installation is in sync', function () {
    expect(syncReport())->toContain('Everything stored matches the code.');
});

test('a permission in the code but not in the database refuses everyone but the owner, and the report says why', function () {
    // The case the command is for: a module gained a permission, and nobody
    // stored it. Nothing fails loudly — the screen just refuses.
    Permission::findByName('customers.view', Area::Admin->guard())->delete();

    $manager = AdminUser::factory()->create();
    $manager->assignRole('manager');
    $this->actingAs($manager, Area::Admin->guard())->get(route('admin.customers.index'))->assertForbidden();

    actingAsAdmin();
    $this->get(route('admin.customers.index'))->assertOk();

    expect(syncReport())
        ->toMatch('/customers\.view\W+in the code, not in the database/')
        ->toMatch('/manager\W+should have customers\.view/');
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
    Permission::findByName('customers.view', Area::Admin->guard())->delete();

    $this->artisan('app:sync')
        ->expectsConfirmation('Fix these 4 now?', 'yes')
        ->expectsOutputToContain('Everything stored now matches the code.')
        ->assertSuccessful();

    $manager = AdminUser::factory()->create();
    $manager->assignRole('manager');
    $this->actingAs($manager, Area::Admin->guard())->get(route('admin.customers.index'))->assertOk();
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
        CatalogServiceProvider::class, PurchasesServiceProvider::class, SalesServiceProvider::class,
    ]))]);
    $running = app(ModuleRegistry::class)->all()->except(['catalog', 'purchases', 'sales'])->values()->all();

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

test('a route cache built before the routes changed is reported', function () {
    $cache = sys_get_temp_dir().'/routes-cache-'.uniqid().'.php';
    file_put_contents($cache, '<?php return [];');
    touch($cache, strtotime('2000-01-01'));

    // Laravel decides once, at boot, whether routes are cached, and keeps the
    // answer; the path is read when asked.
    $_SERVER['APP_ROUTES_CACHE'] = $cache;
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
