# Adding a module

One folder and one line of configuration. No shared file learns the module's
name, and removing the line disables everything the module registers. Its data
— tables, rows, permission records — stays until it is removed deliberately.

The example below is a hypothetical *Deliveries* module. For real ones to copy,
read `app/Modules/Customers` (a directory with a published contract and an
event) and `app/Modules/Accounts` (a ledger that depends on it).

## 1. The folder

```
app/Modules/Deliveries/
├── DeliveriesServiceProvider.php     the module itself
├── Authorization/
│   └── DeliveryPermission.php        what may be done with it
├── Contracts/                        ← the only public folder
│   ├── DeliveryRepositoryInterface.php  its data: find, findMany, save, delete
│   ├── DeliveryBookingInterface.php  an operation with rules, if it has one
│   ├── Data/                         the DTOs those methods take and return
│   ├── Enums/                        the enums they mention
│   ├── Events/                       what it announces
│   └── Exceptions/                   what its interfaces throw
├── Domain/                           ← private: the business
│   ├── Actions/  Listeners/  Queries/  Services/
├── Database/                         ← private: the data
│   ├── Migrations/                   its tables
│   ├── Models/                       their Eloquent models
│   ├── Repositories/                 the repositories behind Contracts/
│   └── Factories/                    its fixtures
├── Areas/                            ← its faces
│   ├── Admin/
│   │   ├── routes.php                its admin screens (declared)
│   │   ├── Controllers/
│   │   └── Requests/
│   └── Frontend/
│       └── routes.php                its screens on the site, if any (declared)
├── Console/                          its commands, named app:deliveries:…
├── View/Components/Admin/            the class behind a component that needs data
├── Config/deliveries.php             its defaults (declared)
└── Resources/
    ├── lang/ar/module.php            its strings, in Arabic…
    ├── lang/en/module.php            …and in English, with the same keys
    └── views/admin/ · views/frontend/  deliveries::admin.… — views, <x-…>, Livewire
```

A component is its template alone until it needs data. Then it gets a class
that mirrors its view: `<x-deliveries::admin.route-card>` is
`View/Components/Admin/RouteCard.php` beside `Resources/views/admin/route-card.blade.php`.
The class prepares; the template only renders — a test fails on a template
that reaches for the container, the user, the request, the session, or a class.

Create only the folders the module uses. Views, strings, and migrations are
picked up by convention. Routes and config are **declared** in the provider —
nothing looks for them on disk, and a test fails if a declared file is missing
or a present one is undeclared.

The folder name, in lowercase, is the module's **key**: `deliveries`. It is the
view, translation, and config namespace, the URL segment (`/admin/deliveries`),
the route-name segment (`admin.deliveries.…`), and the prefix of every
permission. It is never renamed.

## 2. The provider

```php
namespace App\Modules\Deliveries;

final class DeliveriesServiceProvider extends ModuleServiceProvider implements
    DependsOnModulesInterface, ProvidesAdminNavigationInterface, ProvidesPermissionsInterface
{
    protected ?string $config = 'Config/deliveries.php';

    protected array $routes = ['admin' => 'Areas/Admin/routes.php'];

    /** What other modules may resolve. Each implementation is #[Scoped]. */
    public array $bindings = [
        DeliveryRepositoryInterface::class => DeliveryRepository::class,
        DeliveryBookingInterface::class => DeliveryBooking::class,
    ];

    public function key(): string
    {
        return 'deliveries';
    }

    public function dependsOn(): array
    {
        return ['customers'];
    }

    public function permissions(): array
    {
        return DeliveryPermission::cases();
    }

    public function adminNavigation(): NavigationSection
    {
        return new NavigationSection($this->name(), [
            new NavigationItem(
                label: 'deliveries::module.navigation.deliveries',
                route: 'admin.deliveries.index',
                icon: 'truck',
                permission: DeliveryPermission::View,
            ),
        ]);
    }

    public function boot(): void
    {
        Event::listen(SaleCompleted::class, ScheduleDelivery::class);
    }
}
```

Everything is ordinary Laravel: `$bindings` for contracts, `Event::listen()` in
`boot()` for reacting, `$this->commands()` for console commands — each in
`Console/`, named `app:deliveries:…`. Laravel creates
the provider. `register()` is final — it wires the module's shape — so anything
of the module's own that belongs in `register()` goes in `registerModule()`.
Implement only the interfaces the module needs:

| | |
| --- | --- |
| `ProvidesAdminNavigationInterface` | a section in the admin sidebar |
| `ProvidesPermissionsInterface` | its permission enum |
| `ProvidesAdminSettingsInterface` | a tab on the settings screen |
| `DependsOnModulesInterface` | the modules it cannot work without |

`name()` defaults to `{key}::module.name`. A sidebar section may use the
module's name as its heading, or share one of the shell's —
`shell.navigation.group.operations` — and the sidebar merges modules that
share a heading.

## 3. The permissions

```php
enum DeliveryPermission: string implements PermissionInterface
{
    case View = 'deliveries.view';
    case Manage = 'deliveries.manage';

    public function label(): string
    {
        return __('deliveries::module.permissions.'.Str::snake($this->name));
    }

    public function group(): string
    {
        return 'deliveries';
    }
}
```

Every value starts with the module's key. Then decide who gets them, in
`config/roles.php` — `'manager' => [..., 'deliveries.*']` — not in code.

## 4. The routes

```php
// app/Modules/Deliveries/Areas/Admin/routes.php

Route::get('/', DeliveryIndexController::class)
    ->middleware('can:'.DeliveryPermission::View->value)
    ->name('index');
```

The shell loads this file inside `/admin/deliveries` and `admin.deliveries.`,
behind the admin guard and the area check. The route above is therefore
`/admin/deliveries`, named `admin.deliveries.index`. A module cannot step
outside that namespace.

Give every screen route the same `can:` permission its sidebar item names —
hiding a menu entry and closing its URL are one statement.

A screen that is not built yet points at the shell's placeholder:

```php
Route::get('/', PlannedSectionController::class)
    ->defaults('description', 'deliveries::module.planned.deliveries')
    ->middleware('can:'.DeliveryPermission::View->value)
    ->name('index');
```

## 5. The strings

```php
// Resources/lang/ar/module.php — and the same keys in en/
return [
    'name' => 'التوصيل',
    'navigation' => ['deliveries' => 'التوصيل'],
    'permissions' => ['view' => 'عرض التوصيلات', 'manage' => 'إدارة التوصيلات'],
    'planned' => ['deliveries' => 'جدولة التوصيل ومتابعته.'],
];
```

Permission keys are the enum case names in snake case (`View` → `view`). A test
fails if the Arabic and English files do not define the same keys.

## 6. Enable it

```php
// config/modules.php
'enabled' => [
    // …
    DeliveriesServiceProvider::class,
],
```

Then bring what is stored in line with it — its tables and its permissions —
and do the same whenever the module changes:

```
php artisan app:sync deliveries    shows what is behind, then asks before fixing it
php artisan app:module:show deliveries    what it declares, and what of it is stored
```

A new permission goes to every role whose pattern in `config/roles.php`
covers it — `'manager' => ['deliveries.*']` gives the manager
`deliveries.cancel` the day it exists and is seeded. Edit the file only when a
role should get something its patterns do not.

## 7. Rules that are easy to break

- **Only `Contracts/` is public.** Another module never sees your models,
  actions, or services — it uses your `DeliveryRepositoryInterface` (`find`,
  `findMany`, `save`, `delete`, DTOs in and out) and your services. Give the
  repository a bulk read (`findMany()`) so a list is one query, and keep every
  rule and event out of it: a business step saves through it, then announces.
  No relations across modules, and never `resolveRelationUsing()`.
- **`#[Scoped]` on what you publish.** Every repository and service in
  `$bindings` is one instance per request. Actions and listeners are plain
  classes. Never `#[Singleton]`.
- **Inside the module, use your models directly** when the work is local — a
  locked update, a transaction. Never check through `find()` and then write
  through `save()`: two requests can pass the same check.
- **Events wait for the commit.** Every event in `Contracts/Events/` implements
  `ShouldDispatchAfterCommit`.
- **The provider is a declaration.** No queries and no file reads in
  `register()` or `boot()` — `RenderCostTest` fails otherwise.
- **Nothing of yours goes in a shared file.** Not `lang/`, not `config/admin.php`,
  not `database/migrations/`.

## 8. When it is no longer wanted

Take its line out of `config/modules.php` — that **disables** it; its data
stays, and `php artisan app:module:list` shows it as *disabled — its data
stays*. To remove the data too:

```
php artisan app:module:uninstall deliveries --dry-run
php artisan app:module:uninstall deliveries
```

A module that depends on it must be uninstalled first. Its tables and
permissions are removed without the module writing anything. If it keeps
something elsewhere — files it stored, a remote account, logins it handed out
— implement `UninstallableInterface` on the provider, as
`CustomersServiceProvider` does:

```php
public function leftovers(): array   // what is there now, one line each
{
    return array_map(fn ($file) => "stored file {$file}", Storage::files('deliveries'));
}

public function uninstall(): void    // runs before its tables are dropped
{
    Storage::deleteDirectory('deliveries');
}
```

Both run only while its tables exist, so both may read them. Right after
`uninstall()`, `leftovers()` is asked again; anything still listed stops the
uninstall there, with the tables untouched.

Then delete its folder with `git rm`.

## 9. Then run

```
vendor/bin/pint --format agent
vendor/bin/phpstan analyse
php artisan test --compact
```

`ArchitectureTest` tells you if the module reaches the wrong way, touches
another module's `Domain/`, lists a screen nothing routes, forgets a
permission on a route, uses an undeclared dependency, makes a cycle, or names
a command outside `app:deliveries:`.
