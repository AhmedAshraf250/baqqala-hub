# Baqqala — Architecture

> **Start here.** Three layers, arrows pointing down only: **Modules** (what the
> system is about) sit on **Areas** (who is looking) sit on the **Foundation**
> (how anyone reaches it). A module is an ordinary Laravel service provider in
> its own folder, enabled by one line in `config/modules.php`, and it owns
> everything about itself — screens, strings, tables, settings, permissions.
> `ArchitectureTest` fails the build when a layer reaches the wrong way.
>
> **To place a new file**, ask: *would this survive becoming a school system?*
> Yes → foundation or shell. No → module.

This file explains *why*. The rules themselves, short, are in
[`AGENTS.md`](AGENTS.md). The recipe for a new module is
[`docs/adding-a-module.md`](docs/adding-a-module.md), and the AdminLTE working
reference is [`docs/adminlte.md`](docs/adminlte.md).

A grocery-shop management system — catalog, stock, customers, and the running
tabs customers settle later — built so the foundation can be lifted whole and
retargeted at a school, a pharmacy, or a clinic. Laravel 13 · Livewire 4 ·
Fortify · spatie/laravel-permission · AdminLTE 4 · Flux.

---

## 1. Three layers

```
        ┌──────────────────────────────────────────────┐
        │  Modules          what this system is about  │
        │  app/Modules/{Catalog, Customers, …}         │
        └──────────────────────────────────────────────┘
                            │ built on
        ┌──────────────────────────────────────────────┐
        │  Areas            who is looking at it       │
        │  app/Admin  ·  app/Frontend                  │
        └──────────────────────────────────────────────┘
                            │ built on
        ┌──────────────────────────────────────────────┐
        │  Foundation       how anyone reaches it      │
        │  app/Foundation                              │
        └──────────────────────────────────────────────┘
```

A module may use the shells and the foundation. A shell may use the
foundation. Nothing points up — **with no exemptions**. The design this one
replaced had one: the foundation's capability interfaces imported the admin
shell's value objects, and the test that should have caught it skipped those
files. An exemption in the test is how a rule stops being true without anyone
noticing.

**The test for a layer:** *if this became a school system tomorrow, would this
file survive unchanged?* `Money` would (a school charges fees); so would the
guards, the session separation, and the component library. A product would not,
and nor would the permission `catalog.view`.

`config/` is the one place allowed to name everything. It is where the product
is *assembled*: `config/modules.php` says which modules run, `config/roles.php`
says who may do what. Code in the shells never names a module to express either.

---

## 2. Directory map

```
app/
├── Foundation/                        never names a module or a shell
│   ├── FoundationServiceProvider.php  registers the modules; app defaults
│   ├── Area/Area.php                  the two areas, and which one a login opens
│   ├── Console/                       app:sync, and its section of php artisan about
│   │   └── Modules/                   app:module:list · show · uninstall
│   ├── Contracts/Modules/             what other layers implement: DependsOnModulesInterface ·
│   │                                  UninstallableInterface · UninstallStepInterface · SyncStepInterface
│   ├── Http/Middleware/               BindAreaSession · SetLocale
│   ├── Identity/                      User · AdminUser · FrontendUser, scopes, validation rules
│   ├── Localization/                  LocalePreference
│   ├── Modules/                       ModuleServiceProvider · ModuleRegistry (what runs) ·
│   │                                  ModuleInspector (every module, and what of it is stored) ·
│   │                                  Uninstall/ · Sync/
│   ├── Money/                         Money · MoneyCast
│   └── helpers.php                    text_direction() · is_rtl()
│
├── Admin/                             the admin shell
│   ├── AdminServiceProvider.php
│   ├── Authorization/                 permissions and roles
│   ├── Console/                       app:admin:grant, and its section of php artisan about
│   ├── Contracts/                     what a module implements to add to the area:
│   │                                  Authorization/ · Navigation/ · Settings/
│   ├── Http/                          Controllers · Middleware · Requests
│   ├── Navigation/                    the sidebar tree
│   ├── Settings/                      the settings tabs
│   └── View/Components/               Layout/: Sidebar · Breadcrumbs · ContentHeader · PageTitle · UserMenu · Ui/: FlashMessages
│
├── Frontend/                          the frontend shell: the site, and /account
│   ├── FrontendServiceProvider.php    Fortify lives here: it serves this area only
│   ├── Actions/                       Fortify's password reset, sign-out
│   ├── Http/                          Controllers · Middleware
│   └── View/Components/               UserMenu · AuthSessionStatus
│
└── Modules/{Name}/                    the business — see §3
```

`resources/views/admin/` and `resources/views/frontend/` are the `admin::` and
`frontend::` namespaces: each area's screens under `page/`, its component
library under `layout/`, `ui/`, `form/`, `table/`. There is no third namespace;
`resources/views/flux/` holds only Flux's own component overrides, which Flux
looks for there by name.

**A template renders; it never fetches.** Magento left Blocks for view models
because a template that reaches into the application is logic nobody can test,
hidden in markup. Laravel's view model is the class-based component: the class
takes what it needs through its constructor and prepares the data, and the
template shows it. `<x-admin::layout.sidebar>` is
`App\Admin\View\Components\Layout\Sidebar` when that class exists, and the
template alone when it does not — so a component starts as markup and grows a
class the day it needs data. Each shell tells Blade where its classes are with
`Blade::componentNamespace()` in its provider; a module's are found in its own
`View/Components/`, registered with its views.

A screen's data comes from its controller (`SettingsController` hands the
settings page its tabs, `HomeController` the front page whether anyone is
signed in), its Livewire component, which is already a class, or — for the
sign-in screens Fortify's controllers render — the view closure registered in
`FrontendServiceProvider`. A test fails on a template that reaches for the
container, the signed-in user, the request, the session, or a PHP class.

---

## 3. Modules

### A module is a service provider

```php
final class AccountsServiceProvider extends ModuleServiceProvider implements
    DependsOnModulesInterface, ProvidesAdminNavigationInterface, ProvidesPermissionsInterface
{
    protected ?string $config = 'Config/accounts.php';
    protected array $routes = ['admin' => 'Areas/Admin/routes.php'];

    public array $bindings = [
        AccountRepositoryInterface::class => AccountRepository::class,
        AccountLedgerInterface::class => AccountLedger::class,
    ];

    public function key(): string { return 'accounts'; }

    public function boot(): void
    {
        Event::listen(CustomerAdded::class, OpenAccountForNewCustomer::class);
    }

    // dependsOn(), permissions(), adminNavigation() …
}
```

Binding a contract, listening for an event, offering a console command: these
are what a service provider already does — `$bindings`, `Event::listen()`,
`$this->commands()`. The design this replaced wrapped each one in an interface
of our own (`ProvidesBindingsInterface`, `ProvidesEventListenersInterface`, …):
twelve interfaces, five of them copies of Laravel. Any Laravel developer knows
how to write a service provider; nobody knew ours.

Laravel creates each module itself — the foundation hands it the class name
with `$this->app->register($class)` and keeps the instance it gets back.
`register()` is final in `ModuleServiceProvider`: it wires the module's shape and
then calls the module's `registerModule()` hook, so no module can forget
`parent::register()` and lose its views and config without a word. `boot()`
stays the module's own.

**The provider is the one place that says what the module brings** — the role
Magento gives `etc/`. What might or might not exist is *declared*: `$config` and
`$routes` (by area). What always has the same shape is *convention*, registered
as paths only: views, strings, migrations. Nothing is ever checked for on disk
at runtime; a test fails the build if a declared file is missing, or if a
module has a routes or config file it did not declare.

```

app/Modules/Accounts/
├── AccountsServiceProvider.php
├── Contracts/                  ← public (see below)
│   ├── AccountRepositoryInterface.php   its data: find, save
│   ├── AccountLedgerInterface.php       an operation with rules: post
│   ├── Data/  Enums/  Events/  Exceptions/
├── Domain/                     ← private: the business
│   ├── Actions/  Listeners/  Services/
├── Database/                   ← private: the data
│   ├── Migrations/             its tables
│   ├── Models/                 their Eloquent models
│   ├── Repositories/           the repositories behind Contracts/
│   └── Factories/              its fixtures
├── Areas/                      ← its faces, one folder per area
│   ├── Admin/                  routes.php (declared) · Controllers/ · Requests/
│   └── Frontend/               the same, when it has screens on the site
├── Authorization/              its permission enum
├── Console/                    its commands, named app:accounts:…
├── View/Components/{Admin,Frontend}/  the classes behind its components that need data
├── Config/accounts.php         its defaults, as config('accounts.…') (declared)
└── Resources/
    ├── lang/{ar,en}/module.php its strings, as __('accounts::module.…')
    └── views/{admin,frontend}/ views, components, and Livewire screens,
                                as accounts::admin.… / accounts::frontend.…
```

The root reads as four things — **`Contracts/`** what is public, **`Domain/`**
the business, **`Database/`** the data, **`Areas/`** the faces — plus the
module's vocabulary and settings. `Areas/` holds only classes and route files;
a module's views stay in `Resources/views/`, split by area, so the view's own
name says which area it belongs to. They are registered the way the areas' are:
as views, as `<x-accounts::admin.…>` components, and as Livewire screens.

This is the fix for the biggest silent contradiction in the old design: a
module could not own its tables, strings, config, or fixtures, so they lived in
five shared places, and retargeting the product meant editing all of them.

**Removing a module's line from `config/modules.php` disables it** — and that
word is chosen carefully. Its code stops loading: routes, screens, strings,
permissions, sidebar, config, and migration path are all gone, and
`ArchitectureTest` proves it by booting a fresh application without Catalog.
Its **data stays**: its tables and rows, the foreign keys other tables hold on
them (disable Accounts and a customer with a ledger still cannot be deleted —
the database remembers), and its rows in the permission tables. Seeding again
while it is disabled takes its permissions off every role, and re-enabling it
and seeding restores them from `config/roles.php`. A config edit should never
delete data.

**Uninstalling** is the separate, deliberate step:

```
php artisan app:module:uninstall catalog --dry-run   what it would remove
php artisan app:module:uninstall catalog             remove it, after typing its key
```

It checks the module's state before it touches anything: refused while the
module is enabled, and while a module that depends on it is still installed —
Customers cannot go while Accounts' ledger points at it, nor while Sales, with
no tables at all, still has its permissions and could be enabled again
expecting it. Uninstall what depends on a module first, as a package manager
would. Then, in order:

1. **The module's own leftovers**, while its tables can still be read. A
   module that keeps something outside its tables implements
   `App\Foundation\Contracts\Modules\UninstallableInterface`: `leftovers()` says what is
   there, `uninstall()` removes it. The developer who made the leftovers writes
   this; the tool decides when it runs. Customers is the real case: the portal
   logins it hands out are rows of `users`, which the foundation owns and no
   migration of the module drops — left behind, they would still open the
   portal. It finds them through `customers.user_id`, which is why both
   methods run only while the module's tables exist. Right after
   `uninstall()` the tool asks `leftovers()` again, and stops there, tables
   untouched, if anything is left. A module that keeps nothing outside its
   tables — Accounts, Catalog — writes nothing.
2. **What the shells hold for it.** Each layer contributes a step through
   `App\Foundation\Contracts\Modules\UninstallStepInterface`, tagged in its
   own provider, so the foundation removes what it does not know about without
   naming the layer. The admin shell's step removes the module's `{key}.*`
   permissions and, through the tables' foreign keys, their grants.
3. **Its tables**, by rolling back its migrations.

What there is to remove is the module's **footprint**
(`App\Foundation\Modules\ModuleFootprint`): its tables and their rows, its
migrations, what the shells hold, its leftovers — the same thing
`app:module:show` reports. Afterwards the tool reads it again and fails
unless it is empty. It removes data, never code: the module's folder and its line are in git, where
deleting them can be undone. Prohibited in production with the other
destructive commands.

### What a module adds to an area

Only what an area genuinely *collects* from every module is an interface. Each
layer keeps its interfaces in its own `Contracts/` — the foundation's, each
shell's, each module's — the way Laravel keeps its own in `Illuminate\Contracts`,
and a test fails on an interface anywhere else:

| Interface | Read by |
| --- | --- |
| `App\Admin\Contracts\Navigation\ProvidesAdminNavigationInterface` | the sidebar and breadcrumbs |
| `App\Admin\Contracts\Authorization\ProvidesPermissionsInterface` | the permission registry |
| `App\Admin\Contracts\Settings\ProvidesAdminSettingsInterface` | the settings tabs |
| `App\Foundation\Contracts\Modules\DependsOnModulesInterface` | the foundation, at boot |

Routes are files, not an interface. The shell asks each module for the file it
declared for that area (`routesFor(Area::Admin)`) and loads it **inside a prefix
and a name of the module's own** — `/admin/catalog/…`, `admin.catalog.…` — so a
module cannot step outside its namespace or widen what guards it: both are set
around it, not by it. On the frontend the prefix is the site's own root —
`/catalog/…`, `frontend.catalog.…` — and a test fails if any two routes answer
the same URL, since Laravel lets a later one silently replace an earlier one.

A module that declares a dependency on one that is not enabled stops the
application at boot, by name: *"The [accounts] module needs [customers]"*.
Otherwise the failure would come much later, far from its cause.

### Talking to another module

**`Contracts/` is public. Everything else is private, with no exceptions.**

`Contracts/` holds the interfaces a module promises, and every type that appears
in them: `Data/` for the DTOs that cross, `Enums/`, `Events/` for what it
announces, and `Exceptions/` for what its interfaces throw. An exception is part
of a contract as much as a return value is — the caller has to catch it.

There are three ways across, and the name says which:

| | named | for |
| --- | --- | --- |
| **A repository** | `CustomerRepositoryInterface` | a module's data: `find`, `findMany`, `save`, `delete` — DTOs in and out |
| **A service** | `AccountLedgerInterface` | an operation with rules of its own — the ledger's `post()` locks a row and enforces the credit limit, so it is not a repository method |
| **An event** | `CustomerAdded` | announcing something, without caring who listens |

A repository is the ordinary kind, as Magento has them: it stores and fetches,
and knows no rule. Its methods are data verbs (`find…`, `get…`, `save`,
`delete`), and a test holds that line — the moment a "repository" method needs
a rule, it has become a service wearing the wrong name. The business steps sit
on top of it: `CreateCustomer` saves through the repository and then announces
`CustomerAdded`; the repository itself announces nothing.

**The repository is the boundary, not a toll on every query.** Other modules
only ever reach a module's data through it. Inside the module, code uses the
Eloquent models directly whenever the work is local and clear: a locked update
inside a transaction, a query shaped for one screen. `GrantPortalAccess` is the
case that proved it — routed through the repository's `find()` then `save()`,
two grants racing for one customer could both pass the check and leave a login
belonging to nobody. As one locked, conditional update on the model, the second
is refused and its login rolled back.

A repository offers only what its data permits. The accounts repository has
`save()` for an account's settings but never writes what the customer owes —
that is the ledger's total, changed only by `post()` — and it has no `delete()`,
because an account with a history is never deleted.

**Models never cross.** Handed another module's Eloquent record, a caller can
save it, delete it, or lazily load relations it should not reach. Handed a
readonly DTO, it can only read. So:

- a DTO is built by the module's own model (`Customer::toData()`), never by a
  static on the DTO — which would put the model in the public folder;
- another module holds only the id: `CustomerAccount` has a plain `customer_id`,
  and the repositories read in bulk — `findMany()`, `findManyByCustomers()` — so a
  screen listing many rows resolves them in one query, not one per row;
- there are **no cross-module relations**, and none attached at runtime.
  `resolveRelationUsing()` stores a closure on the model class on every request:
  one module needing fifty of them would carry fifty closures per request, and a
  dependency no declaration shows. It fails the build anywhere in `app/`. The
  old `$customer->account` also handed an Accounts model to anyone holding a
  customer.

Test fixtures are the one different case: an account row cannot exist without a
customer row, so a module's `Database/` may use a *declared dependency's*
`Database/` — fixtures building fixtures. Runtime code never does.

**Events wait for the commit.** Every published event implements
`ShouldDispatchAfterCommit`, so it is held until the *outermost* transaction
commits. When Sales saves a sale and posts its ledger entry in one transaction
and the sale then fails, the entry never happened — and nobody is told it did.

### How long an object lives

**`#[Scoped]` — one instance per request — where there is a reason for it**:
the repositories and services a module publishes, which other modules ask for
repeatedly in one request, and any object that keeps state for the request —
`AdminNavigation`, `PermissionRegistry`, `AdminSettings`. Each class says so on
itself with Laravel's attribute: every caller in a request shares the object,
and the next request gets a new one, including under a long-lived worker such
as Octane.

**Actions and listeners are plain classes.** They hold nothing between calls,
so the container builds one when asked and nothing is gained by sharing it;
scoping them would add a rule without a reason.

- **Never `#[Singleton]` and never `$singletons`.** A singleton outlives the
  request, so anything it remembers — a filtered menu, a cached lookup — reaches
  the next visitor. The one application-lifetime object is `ModuleRegistry`,
  which is built once from configuration at boot and never changes.
- **A per-request object may memoise.** `AdminNavigation` and
  `PermissionRegistry` build once and return the same result to every reader in
  the request; that is safe precisely because the object does not outlive it.
- **A class that must be new every time is made by a factory.** The factory is
  itself `#[Scoped]`; its `make()` returns a new object on each call. None is
  needed yet — nothing today holds per-use state — and a class that would is a
  signal to look at the design before reaching for one.

`ArchitectureTest` fails if a published implementation is not scoped, or if
anything in `app/` is a singleton. `RenderCostTest` proves
the lifetime itself: the same object within a request, and a new one after the
framework clears the scope between requests.

### `Contracts`, not `Api`

Magento calls this folder `Api/` because Magento generates its REST API from
those interfaces (`webapi.xml`) — the name is honest there. Laravel does not do
that, and in Laravel "API" means the HTTP one: the `api` routes file, API
Resources, Sanctum. A module here will have one of those eventually — a mobile app, a POS
device — and `Api/` would then mean two things. Laravel itself uses
`Illuminate\Contracts` for exactly our meaning.

### What a request costs

Every request registers every module: PHP shares nothing between requests. The
cost is kept flat by keeping modules **declarations**:

- **No I/O at runtime.** Nothing is checked for on disk: files are declared,
  and the build proves they exist. Views, strings, and migrations are
  registered as paths and opened only when a page uses them; views are added to
  the finder directly, not through `loadViewsFrom()`, which checks a `vendor/`
  directory per module per request. With `config:cache` and `route:cache` —
  production — module config and route files are not even opened.
- `RenderCostTest` boots the real application with and without the modules and
  fails if a module adds more than 2 ms, or if booting issues a single query.

Measured with 200 generated modules — each with permissions, a sidebar
section, a published repository, and a listener — on a real boot:

| | 7 modules | 207 modules | 207, config cached |
| --- | --- | --- | --- |
| booting the application | 8.13 ms | 11.04 ms | 6.86 ms |
| building the sidebar and 419 permissions (admin pages only) | 0.18 ms | 1.12 ms | 1.09 ms |
| memory | 0.8 MB | 1.86 MB | |

About 15 µs per module, and with the configuration cached — as production runs
— two hundred modules boot faster than seven without it. The part that grows
with the count is the sidebar on admin pages; if it ever matters, the first
step is to cache the unfiltered tree and filter it per administrator, and after
that a compiled module manifest, the way Magento compiles its configuration.
Neither is built, because the numbers do not ask for them.

The heavy lifting is Laravel's own caches, which modules must not break:
`config:cache` (verified: module defaults are captured) and `route:cache`
(verified by a test that actually caches routes). Memory is per worker, not
per visitor: a worker handles one request at a time and reuses its memory for
the next.

---

## 4. Areas and identity

### Two areas: the back office and the frontend

| | admin | frontend |
| --- | --- | --- |
| Is | the back office | everything the public faces |
| URLs | `/admin/…` | `/` (the site) · `/login` · `/account/…` (the signed-in visitor's pages) |
| Views | `admin::` — AdminLTE, Bootstrap | `frontend::` — Flux, Tailwind |
| Guard · model | `admin` · `AdminUser` | `web` · `FrontendUser` |
| A module adds | `Areas/Admin/routes.php` | `Areas/Frontend/routes.php` |

The frontend is named for the surface, not for who uses it. For this product
the signed-in visitor is a customer following their tab; for a school, a
parent. Being a customer is a *section* of the frontend — `/account` — and the
Customers module is one of the things that can compose it, not the area
itself. A module's frontend screens are public by default (a product page), and
a screen that is the visitor's own adds the account's guard itself.

"Which area is this?" is answered once, by `App\Foundation\Area\Area`: from a
URL, for a session, for a guard, for a login route and a home. It is also the
`users.area` column — a login opens exactly one area. Nothing else tests a URL
prefix by hand.

`Area::Admin->user()` asks the admin guard by name. The default guard is the
frontend's, so admin code reaching for `Auth::user()` would be relying on
middleware having switched it.

### `users` and `customers`

| | `users` | `customers` |
| --- | --- | --- |
| Answers | *can this login sign in, and to which area?* | *who does the shop do business with?* |
| Created by | `app:admin:grant`, or `GrantPortalAccess` | the shop, at the counter (`CreateCustomer`) |
| Required? | no — most customers never have one | yes, for anyone with a tab |

Most customers are **names in the ledger**, with `customers.user_id` null
forever. There is no public sign-up: a customer's identity starts inside the
shop, and `GrantPortalAccess` hands them a login if and when the shop decides.
A public form would mint a second, unlinked identity for someone already in the
books — and nobody signs themselves up as a pupil or a patient either.

`area` is **not fillable**. It is set only by the area's own model — an
`AdminUser` is created with `area = admin`, a `FrontendUser` with `frontend` —
and the column defaults to the least privileged. A form that passes its input
straight to `create()` cannot promote anyone.

### Three barriers

1. **Separate session cookies.** `BindAreaSession` points the session at the
   request's area — `baqqala_session` or `baqqala_session_admin` — before it
   starts. This barrier matters most: once, a customer's
   `auth.password_confirmed_at` unlocked an admin screen, because both areas
   shared one session. Now the other area's keys are unreachable, not renamed.
2. **Separate guards.** `web` and `admin`, backed by `FrontendUser` and
   `AdminUser` — one `users` table, each model scoped to its own area.
   `AdminUser::find($customerId)` returns null.
3. **The area check.** `EnsureUserIsAdmin` / `EnsureUserIsFrontendUser` re-check
   the login's area anyway.

**All three hold on Livewire updates too.** Livewire sends every component
action to one endpoint and re-runs only the middleware it is told to; the area
checks and the admin password confirmation were not on its list, so an expired
confirmation kept a confirmed screen working. They are registered as persistent
middleware now, and `RequireAdminPasswordConfirmation` *throws* its refusal:
Livewire acts only on a redirect it gets back, and silently dropped the JSON
refusal the middleware used to return. `LivewireIsolationTest` drives the real
update endpoint to prove both.

The Livewire endpoint lives outside `/admin`, which is why the admin cookie's
path is `/` and why `Area::forSession()` falls back to the referrer for that one
endpoint. The referrer only picks which cookie to open; the guard still decides
who the visitor is, and a missing referrer falls back to the frontend.

### Two front doors

- `/admin/login` — ours, on the admin guard (`App\Admin\Http\Controllers\Auth`).
- `/login` — Fortify's, on the frontend guard. Fortify serves the frontend only,
  so it is wired in `FrontendServiceProvider`, and its own responses land
  in the right place through `fortify.home` and `fortify.redirects`.

Each door looks logins up through its own area's model, so the other area's
credentials find no record. A fixed hash is still compared, so timing does not
reveal that the other area has an account for that address. Every auth route is
declared in `routes/auth.php` (`Fortify::ignoreRoutes()`).

The first administrator is created with `php artisan app:admin:grant`, which
refuses to convert a customer's login: that login is the key to their tab in the
portal, and an administrator who also shops here uses a second address.

### Language follows the reader

The chosen language is a cookie of its own (`baqqala_locale`), not a session
key. It used to live in the session; when the sessions were split per area, the
code went on claiming "applies to both areas identically" while choosing English
in the admin left the frontend in Arabic. The switcher posts to
`/admin/locale` — under `/admin` so the request opens the admin session and its
CSRF token — and the choice reaches both areas.

---

## 5. Navigation and authorization

### One tree

`App\Admin\Navigation\AdminNavigation` assembles the sidebar from the shell and
the running modules, once per request (`#[Scoped]`, never a singleton, so one
administrator's filtered menu never outlives the request under Octane). The
sidebar, the breadcrumbs, the content header, and the browser tab title all read
it. Hand-written breadcrumbs are how Settings once claimed Dashboard as its
parent.

Modules may share a heading — Purchases, Sales, and Reports all sit under the
shell's *Operations* — and the tree merges them into one section.

Unbuilt sections are routed to `PlannedSectionController`, which describes each
in the module's own strings. The sidebar shows the product's shape, no link is
dead, and building a section means pointing its route at a real controller.

### Permissions and roles

`spatie/laravel-permission`, scoped to the **admin guard**; customers have no
roles at all.

- **Permissions are enums**, one per module (`CatalogPermission`, …),
  implementing `App\Admin\Contracts\Authorization\PermissionInterface`. A typo in code
  cannot become a silently-false check, and a module takes its vocabulary with
  it when it leaves. Each permission is prefixed with its module's key.
- **Roles are configuration**, in `config/roles.php`, as patterns
  (`catalog.*`). Which roles exist is the product's opinion — a school has no
  cashier — so it sits beside `config/modules.php`, and a pattern for an absent
  module grants nothing. Because a pattern is a string, a test asserts every one
  matches a permission that exists.
- **The unrestricted role** (`owner`) is granted every check by a `Gate::before`
  that returns null for everyone else — a shortcut, not a verdict. A permission a
  module adds tomorrow is the owner's the moment it exists.
- **Hiding a menu item and closing its URL are one statement.** A sidebar item
  names its permission, its route carries `can:{permission}`, and a test asserts
  both.

---

## 6. Money

**An integer count of minor units.** PHP floats cannot represent most decimal
fractions, SQLite stores `DECIMAL` as `REAL`, and this deployment has no
`bcmath` — any one of those corrupts a balance quietly.

`App\Foundation\Money\Money` is immutable and parses strings without passing
through a float; `App\Foundation\Money\MoneyCast` maps a `bigInteger` column to
it; `<x-admin::ui.money>` displays it with digits kept left-to-right.
`currency.precision` is both the display precision and the minor-unit scale, so
changing it after go-live means migrating every money column.

---

## 7. The account ledger

`customer_accounts.outstanding` is **what the customer owes** — a cached total
of the ledger, written only by
`App\Modules\Accounts\Domain\Actions\PostAccountTransaction`, which in one
transaction locks the row, refuses a debit past the credit limit (or a payment
past the tab, unless `accounts.allow_overpayment`), writes the entry, and writes
the new total. `outstanding` is not fillable. Ledger rows are immutable; a
mistake is corrected by posting its inverse.

| | effect | the counter reads |
| --- | --- | --- |
| `Debit` | raises what is owed | «عليه» — goods taken |
| `Credit` | lowers what is owed | «له» — money handed over |

The accounting pair, because it is neutral across businesses and already spoken
by anyone who audits books. Event names were tried and rejected: `Charge` reads
as "top up", and `Purchase` misleads on a running tab.

An account is opened by `OpenAccount` — when a customer is added, and again on
first posting, so "every customer has an account" does not depend on a listener
never failing.

Other modules read an account through `AccountRepositoryInterface` —
`findByCustomer()`, and `findManyByCustomers()` for many at once, each an
`AccountStanding` — and write only through `AccountLedgerInterface::post()`, with ids and money.
`AccountStanding::wouldBreachLimit()` is the one statement of the credit-limit
rule: the ledger refuses with it, and a caller asking first gets the same answer.
Refusals come
back as `CreditLimitExceeded` / `OverpaymentNotAllowed`, carrying the facts —
customer id, limit, attempted total — not the account record.

---

## 8. Where things go

| Kind | Where | When |
| --- | --- | --- |
| **Action** | `Modules/{Name}/Domain/Actions/` | a use case that changes state — the default |
| **Query object** | `Modules/{Name}/Domain/Queries/` | a read reused across screens, or too complex for a scope |
| **Scope** | on the model | a simple reusable filter |
| **Listener** | `Modules/{Name}/Domain/Listeners/` | reacting to another module's event |
| **Model** | `Modules/{Name}/Database/Models/` | a table, in Eloquent. Private to its module. |
| **Repository** | contract in `Modules/{Name}/Contracts/`, implementation in `Database/Repositories/` | a module's data: find, save, delete. No rules. |
| **Service** | contract in `Modules/{Name}/Contracts/`, implementation in `Domain/Services/` | an operation another module may ask for that has rules of its own |
| **DTO / enum / event / exception that crosses** | `Modules/{Name}/Contracts/{Data,Enums,Events,Exceptions}/` | it appears in a published interface |
| **A business screen** | `Modules/{Name}/Areas/Admin/Controllers/` or `Areas/Frontend/Controllers/`, routed from the declared routes file | the module's face in an area |
| **A shell screen** | `app/{Admin,Frontend}/Http/Controllers/` | exists whatever the business is: dashboard, account, settings, sign-in |
| **A component with data** | `app/{Admin,Frontend}/View/Components/`, or a module's `View/Components/{Admin,Frontend}/` | a component that needs more than its attributes — the sidebar, the user menu |
| **A command** | the layer's `Console/` — `app/Foundation/Console/{Group}/`, `app/{Admin,Frontend}/Console/`, `Modules/{Name}/Console/` | see §9 |

**The foundation serves no pages.** It has middleware — area sessions, language
— but every endpoint belongs to an area or a module. A controller is HTTP and
area-specific, so it is never in `Domain/`: the action does the work, and the
controller, a Livewire screen, a console command, or a job can all call it.

Model wiring uses attributes: `#[UseFactory]`, `#[ObservedBy]`, `#[UsePolicy]`,
`#[ScopedBy]`, `#[Fillable]`.

---

## 9. The console

Every command is under `app:`, so `php artisan list app` is the whole
list, grouped by what each acts on:

| Command | What it does | Lives in |
| --- | --- | --- |
| `app:module:list` | every module, disabled ones too: enabled, disabled with data kept, or not installed; what it depends on; its migrations | `app/Foundation/Console/Modules/` |
| `app:module:show {module}` | one module: provider, folder, what depends on it, what it declares (config, routes by area, contracts, commands), and its footprint | `app/Foundation/Console/Modules/` |
| `app:sync {module?}` | after a module changes: shows what is stored behind the code — migrations not run, permissions or role grants not stored, caches built from older code — then asks, and fixes only on a yes (`--force` for a deploy script) | `app/Foundation/Console/` |
| `app:module:uninstall {module}` | removes a disabled module's footprint — see §3 | `app/Foundation/Console/Modules/` |
| `app:admin:grant` | creates an administrator, or changes one's role | `app/Admin/Console/` |
| `app:customers:grant-portal-access` | hands a customer a portal login | `app/Modules/Customers/Console/` |

Every command starts with `app:` — Laravel's own prefix for an application's
commands — never with the product's name. The group is the layer, as with
permissions and routes: `app:module:` is the foundation's `Console/Modules`,
`app:admin:` the admin shell's, and `app:{key}:` a module's own; a foundation
command about the whole installation sits in `Console/` itself, as `app:sync`
does. A test fails on a command in any other folder,
under any other name, or one nothing registers — a command that has to be
stumbled on is not one anyone runs.

The product's settings and state are not a command of their own: they are
sections of Laravel's `php artisan about`, beside the framework's drivers and
caches. The foundation's (`--only=foundation`) shows the version, languages,
currency, each area's guard, session cookie and logins, and which modules are
enabled, disabled with data kept, or waiting for migrations. The admin shell's
(`--only=admin_area`) shows its roles and whether every permission the
running modules define is stored — a module enabled without reseeding is not.

Most of a module needs nothing after it changes: routes, the sidebar,
settings, strings, and commands are read from its code. What is *stored* can
fall behind — tables, permissions and the roles that hold them, caches — and
`app:sync` asks each layer that stores something. A layer adds its
own step by implementing `App\Foundation\Contracts\Modules\SyncStepInterface` —
`findings()` looks, `fix()` changes — and tagging it in its provider: the
foundation's steps are migrations and caches, the admin shell's permissions
and role grants. `AuthorizationSeeder` is those two admin steps run without
asking, so seeding and syncing cannot disagree. A new permission a module adds is
granted to every role whose pattern in `config/roles.php` already covers it;
the file changes only when a role should get something its patterns do not.

Disabled modules are not registered, so the module commands find them on disk.
That reading happens in `App\Foundation\Modules\ModuleInspector`, which only
the console uses; `ModuleRegistry`, which a request uses, never touches the
disk.

---

## 10. Localisation and configuration

Arabic and English are both first-class. Every user-facing string is a
translation key: the foundation's few in `lang/{locale}/foundation.php`, the
shells' in `lang/{locale}/shell.php`, each module's in
its own `Resources/lang/{locale}/module.php`. A test asserts every Arabic file
and its English twin define exactly the same keys, file by file. Direction comes
from `text_direction()` / `is_rtl()`; AdminLTE's LTR and RTL builds
never load together.

| File | Holds |
| --- | --- |
| `config/modules.php` | which modules run |
| `config/roles.php` | which roles exist and what each may do |
| `config/foundation.php` | what every product reads: version, languages, currency |
| `config/admin.php` · `config/frontend.php` | each shell's own settings: the admin's colour-mode key, page size, search |
| `app/Modules/{Name}/Config/{key}.php` | a module's defaults, declared in its provider — overridden by a `config/{key}.php` of the same name |

Application code never calls `env()` outside a config file.

---

## 11. Known gaps

Found in the review that produced this design, and deliberately not changed
without a decision:

- **`verified` does nothing.** `User` does not implement `MustVerifyEmail`, so
  the `verified` middleware on both areas lets everyone through. Turning it on
  means administrators and portal customers must verify an address before
  entering — a product decision, deferred.
- **No audit trail.** Who changed what, and when, is the largest gap for a
  system that handles money.

Not built yet, in order: the customer directory with Arabic-aware name search
(alef/hamza and taa-marbuta folding), the quick credit-entry form and account
statements, then catalog, suppliers, purchases, and sales.
