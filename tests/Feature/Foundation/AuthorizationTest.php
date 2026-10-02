<?php

use App\Admin\Authorization\AdminPermission;
use App\Admin\Authorization\PermissionRegistry;
use App\Admin\Authorization\RoleDefinitions;
use App\Admin\Authorization\SyncPermissions;
use App\Admin\Authorization\SyncRoleGrants;
use App\Admin\Contracts\Authorization\ProvidesPermissionsInterface;
use App\Admin\Contracts\Settings\ProvidesAdminSettingsInterface;
use App\Admin\Settings\AdminSettings;
use App\Admin\Settings\SettingsSection;
use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\AdminUser;
use App\Foundation\Identity\Models\FrontendUser;
use App\Foundation\Modules\ModuleInspector;
use App\Foundation\Modules\ModuleRegistry;
use App\Foundation\Modules\ModuleServiceProvider;
use App\Modules\Accounts\Authorization\AccountPermission;
use App\Modules\Catalog\Authorization\CatalogPermission;
use App\Modules\Catalog\CatalogServiceProvider;
use App\Modules\Customers\Authorization\CustomerPermission;
use App\Modules\Purchases\PurchasesServiceProvider;
use App\Modules\Sales\SalesServiceProvider;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->seed(AuthorizationSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

/**
 * Give an administrator a role and sign them in on the admin guard.
 */
function actingAsAdminWithRole(string $role): AdminUser
{
    $user = AdminUser::factory()->create();
    $user->syncRoles($role);

    test()->actingAs($user, Area::Admin->guard());

    return $user;
}

test('the permission set is assembled from the running modules', function () {
    $registry = app(PermissionRegistry::class);

    $fromModules = app(ModuleRegistry::class)
        ->providing(ProvidesPermissionsInterface::class)
        ->flatMap(fn (ProvidesPermissionsInterface $module) => $module->permissions())
        ->count();

    expect($registry->all())->toHaveCount(count(AdminPermission::cases()) + $fromModules)
        ->and($registry->values())->toContain(CatalogPermission::View->value)
        ->and($registry->values())->toContain(AccountPermission::Post->value);
});

test('a module owns its own permissions', function () {
    // Removing a module takes its vocabulary with it; nothing central lists it.
    expect(CatalogPermission::View->group())->toBe('catalog')
        ->and(AccountPermission::Post->group())->toBe('accounts')
        ->and(AdminPermission::ManageSettings->group())->toBe('system');
});

test('every declared permission is seeded on the admin guard', function () {
    expect(Permission::query()->where('guard_name', 'admin')->count())
        ->toBe(count(app(PermissionRegistry::class)->values()));
});

test('roles and permissions belong to the admin guard, not the default one', function () {
    expect((new AdminUser)->guardName())->toBe('admin')
        ->and(Permission::query()->where('guard_name', 'web')->count())->toBe(0);
});

test('a customer has no roles at all', function () {
    expect(method_exists(FrontendUser::class, 'hasRole'))->toBeFalse();
});

test('the owner is granted everything without holding each permission', function () {
    $owner = actingAsAdminWithRole('owner');

    foreach (app(PermissionRegistry::class)->all() as $permission) {
        expect($owner->can($permission->value))->toBeTrue("Owner was refused [{$permission->value}].");
    }

    // Including one no role holds — which is what makes a module's new
    // permission the owner's the moment it exists.
    expect($owner->can('a.permission.nobody.was.granted'))->toBeTrue();
});

test('the roles are the product\'s configuration, not code', function () {
    // The shell must not name a module's permission to say what a cashier
    // does; a school ships different roles over the same foundation.
    expect(app(RoleDefinitions::class)->unrestricted())->toBe('owner')
        ->and(array_keys(config('roles.defaults')))->toBe(['owner', 'manager', 'cashier']);
});

test('every grant in the roles file matches a permission that exists', function () {
    // A pattern in configuration is a string, so this is what stands in for
    // the enum: a typo like `acounts.post` fails here, not silently at the
    // counter. A pattern may only match nothing when its module is not
    // installed.
    $available = app(PermissionRegistry::class)->values();
    $installed = app(ModuleRegistry::class)->all()->keys()->all();

    foreach (app(RoleDefinitions::class)->patterns() as $role => $patterns) {
        foreach ($patterns as $pattern) {
            $matches = array_filter($available, fn (string $permission) => Str::is($pattern, $permission));
            $module = Str::before($pattern, '.');

            expect($matches !== [] || ! in_array($module, $installed, true))
                ->toBeTrue("[{$role}] is granted [{$pattern}], which matches no permission.");
        }
    }
});

test('the unrestricted role is one the roles file defines', function () {
    expect(config('roles.defaults'))->toHaveKey(app(RoleDefinitions::class)->unrestricted());
});

test('a cashier is granted only what the counter needs', function () {
    $cashier = actingAsAdminWithRole('cashier');

    expect($cashier->can(AccountPermission::Post->value))->toBeTrue()
        ->and($cashier->can(CustomerPermission::View->value))->toBeTrue()
        ->and($cashier->can(AdminPermission::ManageAdministrators->value))->toBeFalse()
        ->and($cashier->can(CatalogPermission::Manage->value))->toBeFalse();
});

test('a manager runs the shop but does not hand out access', function () {
    $manager = actingAsAdminWithRole('manager');

    expect($manager->can(CatalogPermission::Manage->value))->toBeTrue()
        ->and($manager->can(CustomerPermission::GrantPortalAccess->value))->toBeTrue()
        ->and($manager->can(AdminPermission::ManageAdministrators->value))->toBeFalse();
});

test('a section an administrator may not see is closed at its url too', function () {
    actingAsAdminWithRole('cashier');

    $this->get(route('admin.access.administrators.index'))->assertForbidden();
    $this->get(route('admin.catalog.stock.index'))->assertForbidden();

    $this->get(route('admin.customers.index'))->assertOk();
});

test('the sidebar hides what the administrator may not reach', function () {
    actingAsAdminWithRole('cashier');

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee(__('customers::module.navigation.customers'))
        ->assertDontSee(__('catalog::module.navigation.stock'))
        ->assertDontSee(__('shell.navigation.administrators'));
});

test('a menu group is hidden from whoever lacks its own permission', function () {
    // The access-control group needs `access.view`, and so does every route
    // under it. Shown because one of its children was allowed, it led to 403.
    $role = Role::findOrCreate('reception', Area::Admin->guard());
    $role->givePermissionTo(AdminPermission::ManageAdministrators->value);
    actingAsAdminWithRole('reception');

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee(__('shell.navigation.access'))
        ->assertDontSee(__('shell.navigation.administrators'));

    $this->get(route('admin.access.administrators.index'))->assertForbidden();
});

test('the owner sees every module section', function () {
    actingAsAdminWithRole('owner');

    $response = $this->get(route('admin.dashboard'))->assertOk();

    foreach (['catalog::module.navigation.products', 'customers::module.navigation.customers', 'accounts::module.navigation.accounts', 'shell.navigation.access'] as $label) {
        $response->assertSee(__($label));
    }
});

test('every declared permission has a label in every locale', function () {
    foreach (array_keys(config('foundation.locales.supported')) as $locale) {
        app()->setLocale($locale);

        foreach (app(PermissionRegistry::class)->all() as $permission) {
            // An untranslated key comes back as itself, which contains `::` or
            // starts with `shell.`.
            expect($permission->label())->not->toContain('::')
                ->not->toStartWith('shell.');
        }

        foreach (array_keys(config('roles.defaults')) as $role) {
            expect(__("shell.roles.{$role}"))->not->toBe("shell.roles.{$role}");
        }
    }
});

test('disabling a module takes its permissions off roles, and keeps the records', function () {
    // What the docs promise about disabling, held to: the permission rows stay
    // for when the module comes back, and no role goes on granting what no
    // running module defines.
    expect(Role::findByName('manager', 'admin')->hasPermissionTo('catalog.view'))->toBeTrue();

    // The seeder as it runs when Catalog, and the modules that need it, are
    // not enabled.
    config(['modules.enabled' => array_values(array_diff(config('modules.enabled'), [
        CatalogServiceProvider::class, PurchasesServiceProvider::class, SalesServiceProvider::class,
    ]))]);
    $running = new ModuleRegistry(app(ModuleRegistry::class)->all()->except(['catalog', 'purchases', 'sales'])->values()->all());
    $permissions = new PermissionRegistry($running);
    $registrar = app(PermissionRegistrar::class);

    (new AuthorizationSeeder(
        new SyncPermissions($permissions, app(ModuleInspector::class), $registrar),
        new SyncRoleGrants(new RoleDefinitions($permissions), $permissions, $registrar),
    ))->run();

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(Permission::query()->where('name', 'catalog.view')->exists())->toBeTrue()
        ->and(Role::findByName('manager', 'admin')->fresh()->hasPermissionTo('catalog.view'))->toBeFalse();
});

/**
 * A module that adds one tab to the settings screen.
 */
function settingsContributingModule(): ModuleServiceProvider
{
    return new class(app()) extends ModuleServiceProvider implements ProvidesAdminSettingsInterface
    {
        public function key(): string
        {
            return 'probe';
        }

        public function adminSettings(): array
        {
            return [new SettingsSection('probe-tax', 'probe::admin.counter', 'shell.settings.account', 'shell.settings.account_description')];
        }
    };
}

test('a module\'s settings tab is shown only to who may configure the panel', function (string $role, bool $seesIt) {
    // `settings.manage` was granted to the manager and withheld from the
    // cashier, and checked nowhere: every administrator saw every tab.
    app()->instance(ModuleRegistry::class, new ModuleRegistry([settingsContributingModule()]));
    actingAsAdminWithRole($role);

    $keys = app(AdminSettings::class)->sections()->pluck('key')->all();

    expect(in_array('probe-tax', $keys, true))->toBe($seesIt)
        ->and($keys)->toContain('account', 'security', 'appearance');
})->with([
    'owner' => ['owner', true],
    'manager' => ['manager', true],
    'cashier' => ['cashier', false],
]);

test('the owner is answered before the permission set is loaded', function () {
    // spatie's own `before` is registered first unless the shortcut is in
    // place by the time the Gate is built; it then loaded every permission
    // for an answer the role already gave.
    $owner = actingAsAdminWithRole('owner');
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    expect($owner->can(CustomerPermission::View->value))->toBeTrue()
        ->and(array_filter($queries, fn (string $sql): bool => str_contains($sql, 'permissions')))->toBe([]);
});
