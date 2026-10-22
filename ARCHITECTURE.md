# Baqqala — Architecture

> **Start here.** Three layers, arrows pointing down only: **Modules** (what the
> system is about) sit on **Areas** (who is looking) sit on the **Foundation**
> (how anyone reaches it). A module is an ordinary Laravel service provider in
> its own folder, enabled by one line in `config/modules.php`, and it owns
> everything about itself — screens, strings, tables, settings, permissions.
> `ArchitectureTest` fails the build when a layer reaches the wrong way.
>
> **To place a new file**, ask: *is it about the shop's business?* Yes → that
> business's module. About running the application? → foundation or shell.

This file explains *why*. The rules themselves, short, are in
[`AGENTS.md`](AGENTS.md). The recipe for a new module is
[`docs/adding-a-module.md`](docs/adding-a-module.md), and the AdminLTE working
reference is [`docs/adminlte.md`](docs/adminlte.md).

A grocery-shop management system — catalog, stock, customers, and the running
tabs customers settle later. The business lives in modules and everything
else beneath them, so a business change touches one module and nothing under
it. Laravel 13 · Livewire 4 ·
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

**The test for a layer:** *is this file about the shop's business, or about
running the application?* `Money` is about running it — any screen that shows
an amount needs it — and so are the guards, the session separation, and the
component library. A product is business, and so is the permission
`catalog.view`. The question is about where a change lands, not about selling
the code to someone else: a new rule for debts should touch the accounts
module and nothing beneath it.

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
│   ├── Audit/                         AuditTrail · AuditEntry — the change log: who changed what, and when
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
│   ├── Settings/                      SettingStore · SettingDefinition — what the owner may change, and did
│   ├── Text/                          Numerals · SearchText · PhoneNumber — typed digits, folded search text, stored phones
│   ├── Time/                          LocalClock — the local wall clock and the business day
│   └── helpers.php                    text_direction() · is_rtl() · local_time() · currency_symbol()
│
├── Admin/                             the admin shell
│   ├── AdminServiceProvider.php
│   ├── Authorization/                 permissions and roles
│   ├── Console/                       app:admin:grant, and its section of php artisan about
│   ├── Contracts/                     what a module implements to add to the area:
│   │                                  Authorization/ · Navigation/ · Overlays/ · Search/ · Settings/ · Trash/
│   ├── Http/                          Controllers · Middleware · Requests
│   ├── Navigation/                    the sidebar tree
│   ├── Overlays/                      what modules keep on every screen: AdminOverlays
│   ├── Trash/                         what modules delete into, to be brought back: AdminTrash
│   ├── Search/                        the quick search: AdminSearch, and the screens it finds
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
| `App\Admin\Contracts\Search\ProvidesAdminSearchInterface` | the header's quick search (`App\Admin\Search\AdminSearch`) |
| `App\Admin\Contracts\Trash\ProvidesTrashInterface` | the trash, which lists, restores, and purges through each module's `TrashSourceInterface` (`App\Admin\Trash\AdminTrash`) |
| `App\Admin\Contracts\Overlays\ProvidesAdminOverlaysInterface` | the layout, which keeps each named component on every screen for whoever holds its permission (`App\Admin\Overlays\AdminOverlays`) |
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

**`Contracts/` is public. Everything else is private** — with one written
exception, Reports, at the end of this section.

`Contracts/` holds the interfaces a module promises, and every type that appears
in them: `Data/` for the DTOs that cross, `Enums/`, `Events/` for what it
announces, and `Exceptions/` for what its interfaces throw. An exception is part
of a contract as much as a return value is — the caller has to catch it.

There are three ways across, and the name says which:

| | named | for |
| --- | --- | --- |
| **A repository** | `CustomerRepositoryInterface` | a module's data: `find`, `findMany`, `save`, `delete` — DTOs in and out |
| **A service** | `AccountLedgerInterface` | an operation with rules of its own — the ledger's `charge()` locks a row, prices its lines, and enforces the credit limit, so it is not a repository method |
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
that is the ledger's total, changed only by posting through the ledger — and it has no `delete()`,
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

**Reports read tables; they never write.** A report sums across modules —
what each group owes, which products were taken most, who collected what —
and through repositories that is a query per row, or a sum in PHP. So the
Reports module, alone, reads the other modules' tables directly: with the
query builder (`DB::table()`), never their models, from its query objects in
`app/Modules/Reports/Domain/Queries/`. It writes nothing, anywhere — a test
fails on a write in its code. It declares every module whose tables it reads
(`dependsOn()`), so it stops with them; it names kinds and reasons with their
`Contracts/` enums, and links to their screens by route name. Every total
counts what stands — not a reversal, and not reversed (`Ledger::standing()`).
No other module has this exception: a screen that needs another module's
figures asks its contract.

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
- `RenderCostTest` boots the real application and fails if booting issues a
  single query. Time is not tested: a timing line fails at random on a busy
  machine and proves nothing a query count does not. Reading files at boot is
  a review rule, measured when it is in doubt, like the table below.

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

### On a phone

The panel is the same application on a phone, laid out for one: a list's rows
become cards, filters fold away, the quick note sits in the thumb's corner.
Added to the home screen it opens on its own, without the browser's bar —
`App\Admin\Http\Controllers\ManifestController` describes it, under the
brand's name in the reader's language and the icons in `public/admin-icons/`.
Installing it, and the microphone, need a secure page: `localhost`, or the
site over HTTPS. Nothing is kept for working offline; every screen reads the
shop as it is.

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
path is `/` and why `Area::forSession()` needs the page to say where an update
came from. The admin bundle adds `Area::RequestHeader` (`X-Area: admin`) to
every Livewire request (`resources/js/admin/modules/area-header.js`); only
without it — a file upload, which Livewire sends outside its request pipeline —
does the referrer decide. Relying on the referrer alone broke the admin area
behind any proxy or privacy setting that strips it. Either one only picks which
cookie to open; the guard still decides who the visitor is, and an update that
names no area falls back to the frontend.

The CSRF token follows the same rule. Laravel's `XSRF-TOKEN` cookie is one for
the whole site, so both areas' layouts carry their own session's token in
`<meta name="csrf-token">`, which script reads first.

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

### Language follows the reader, in each area

Each area keeps its own reader's language, the way it keeps its own session:
the shop's staff and its customers are different readers, and one browser can
serve both. `LocalePreference::resolve()` takes the first answer of:

1. the signed-in login's own choice (`users.locale`), so it follows the person
   to another device — and mail sent to them, through `preferredLocale()`;
2. this browser's choice in this area — its own cookie, `Area::localeCookie()`;
3. the browser's language, only where the area allows it
   (`foundation.locales.areas.{area}.negotiate`, off by default: many who read
   Arabic browse on phones set to English);
4. the area's default (`foundation.locales.areas.{area}.default`), then the
   application's.

Each area has its own switcher — `/admin/locale` under `/admin` so the request
opens the admin session and its CSRF token, `/locale` on the site — and both
write through `LocalePreference::remember()`: the area's cookie, and the login
signed in to that area.

It used to be one cookie for both areas, after an earlier session key had left
each area with its own language by accident. That fixed the symptom by joining
the areas: choosing English in the back office turned the site English for the
next customer on the same browser, and the site had no switcher of its own.

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

A screen reached from one in the tree — a customer's page, from the list — is
named in that item's `activeWhen`: the sidebar lights the item for it, and the
trail runs to the item and then names the screen by its own title, «⌂ / العملاء
/ محمد أحمد علي». The profile sits under Settings the same way.

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

**What a person types is what they meant.** A cashier on an Arabic keyboard
types «٣٥٠٫٥٠»; `fromDecimalString()` reads it as 350.50, through
`App\Foundation\Text\Numerals`, which every parser of typed numbers uses. It
was refused outright before. **What a person reads is grouped:**
`toDisplayString()` and `format()` show «48,350.00», grouped on the string, never
through `number_format()` and its float. Inputs keep `toDecimalString()`, which
is what `fromDecimalString()` reads back; the grouped form is refused as input
rather than misread.

**The currency is written in the reader's language.** `currency.symbol` holds
one spelling per language — «ج.م» in Arabic, «EGP» in English — and
`currency_symbol()` gives the reader's, falling back to `currency.code`;
`format()` and every field's suffix go through it, so an English page never
carries the Arabic letters.

---

## 6a. Time, and the settings the owner changes

**Moments are stored in UTC, and read in local time.** `app.timezone` stays
UTC. `App\Foundation\Time\LocalClock` turns a stored moment into the local wall
clock and decides where the business day begins and ends; templates use
`local_time()`. Two things decide it: the time zone (`Africa/Cairo`, with its
daylight saving from PHP's own zone data) and the hour the business day starts.
A shop that closes at two in the morning counts a sale at half past one in the
day it opened, so its daily totals match the till it counts at closing —
midnight unless the owner says otherwise. Changing either rewrites nothing
stored: it changes how moments are shown and grouped into days. Queries for a
day take `dayBounds()`, a UTC range from one day's start to the next, which is
23 or 25 hours long on the days daylight saving moves the clock.

**The owner sets both from the panel**, on the settings screen's business tab,
behind `settings.manage`. It is not a module: modules are what the system is
about, and every screen that shows a date needs the time — a module would make
every other module depend on it. It is not `.env` alone either, because the
owner should not need someone at the server to change it.

The values live in `App\Foundation\Settings\SettingStore`, a `settings` table of
keys and JSON values with who changed each and when, layered over
configuration: `config/foundation.php` says what an installation starts with
(`foundation.time.*`), the store says what the owner changed it to. It is read
once per request, only when something asks — booting issues no query — and the
next request reads the table again.

**Every value the owner may change is declared, never written into a
screen.** A layer describes each one as an
`App\Foundation\Settings\SettingDefinition` — its key, its kind (text, a
number within bounds, on or off, money), its label and help, and its
configured default — reads it through an accessor of its own (Accounts'
`App\Modules\Accounts\Domain\Services\AccountSettings`: what a line with no
product is called, when a debt is late or an account dormant, the time to
correct one's own entry, whether overpayment is kept, a new account's credit
limit), and offers the lot as a tab with `SettingsSection::declared()`. The
shell's `admin::page.settings.declared` pane draws a row for each, checks each
against its bounds, and stores it; a module writes no settings screen at all.

---

## 6b. The change log

Money keeps its own history: the ledger never changes an entry (§7). Everything
else people change — a customer's name or mobile, a credit limit, a price, a
group, a category, a setting — is written down by
`App\Foundation\Audit\AuditTrail` in the step that makes the change, inside its
transaction: `CreateCustomer`, `UpdateCustomer`, `SaveProduct`, `SetCreditLimit`,
the settings tabs. A row of `audit_entries` (`AuditEntry`) is one change: who,
what — `subject`, named by its owner's key (`customers.customer`,
`catalog.product`, `foundation.setting`), and `subject_id` — its name at the
time, and field by field what it was and what it became. The kind of thing,
each field's name, and a value from a list (a unit, a colour) are stored as
translation keys, so the log reads in its reader's language; names and numbers
are stored as written. An edit that changes nothing writes nothing, and a row
is never edited — the model refuses.

The foundation never names a module: each passes its own keys. Disabling a
module keeps its rows; uninstalling it removes them
(`RemoveModuleAuditEntries`). The log is read under *System*, behind
`audit.view`, which a manager holds: by person, by kind of thing, by what was
done, and by period, grouped by business day.

## 6c. Nothing is lost by a click

What matters most cannot be deleted at all: ledger entries are reversed, never
removed (§7), and customers have no delete. Everything people change is in
the change log (§6b), so an old name or price is never lost either.

What can be deleted goes to the **trash** first («سلة المحذوفات», behind
`trash.manage`): a product never sold, an empty category, an empty customer
group, a note from the notebook with its recording. It leaves every list but
is kept, with who deleted it and when, until someone restores it — to where
it was — or deletes it for good after a question. A module offers its kinds
through `ProvidesTrashInterface`; each `TrashSourceInterface` restores and
purges by its own rules, and says no rather than breaking something:

- A sold product is never deleted, only archived. Soft deletion would not
  stop it — it is an update — so `DeleteProduct` asks the database itself,
  which knows every module's foreign keys: the row is removed in a savepoint
  that is always undone, and a refusal means something still names it.
- A category is deleted only when nothing is in it, counting what is in the
  trash, which could otherwise come back into a category that is gone.
- A code held by a product in the trash — a barcode, a SKU — is still taken,
  and the form says where it is instead of «already used».
- The starter catalog does not bring back what the owner deleted; what was
  deleted for good is missing again, and is added.

The change log names both steps: `deleted` (to the trash) and `purged`.

## 7. The account ledger

`customer_accounts.outstanding` is **what the customer owes** — a cached total
of the ledger, written only by
`App\Modules\Accounts\Domain\Actions\PostAccountTransaction`, which in one
transaction locks the row, applies the rules that belong to the money itself,
writes the entry and its lines, and writes the new total with the summary
beside it. `outstanding` is not fillable.

| | effect | the counter reads |
| --- | --- | --- |
| `Debit` | raises what is owed | «عليه» — goods taken |
| `Credit` | lowers what is owed | «له» — money handed over |

The accounting pair, because it is neutral across businesses and already spoken
by anyone who audits books. Event names were tried and rejected as a
*direction*: `Charge` reads as "top up", and `Purchase` misleads on a running tab.

**Every entry also has a kind**,
`App\Modules\Accounts\Contracts\Enums\EntryKind` — what happened: `Charge`
(«أخذ»), `Payment` («دفع»), `OpeningBalance` (what the paper book said), `Discount`
(«سامحته في الباقي»), `Return`, `Reversal`. The direction is stored beside it,
never derived from it, because an opening balance can be owed either way and a
reversal goes against whatever it cancels. The rules the posting action keeps
for everyone: the amount is above zero; the lines add up to it; the credit limit
holds for a charge (not for an opening balance or a correction, which record
what already happened); a payment leaves the shop holding money only when
`accounts.allow_overpayment` is on — the default, because the counter is asked,
payment by payment, whether the rest was change or credit; a discount never goes
below zero.

**Entries are never edited, and never deleted** — the model refuses both. A
void is a reversal of the same amount that points at the entry
(`reverses_id`, unique: an entry is voided once, and the database refuses a
second reversal even from two requests at once); a reversal is never voided
itself. A correction is a reversal and a replacement in one operation, the
replacement pointing at what it replaces (`replaces_id`) — on the same account,
or on another when the entry was recorded against the wrong customer, both
accounts locked in id order. The entries in between are left alone: they were
true when they were posted.

**One save is one operation.** Every request carries an operation id made once
by the screen; the same operation arriving again — a double click, a retry after
the network dropped — returns what the first posted and writes nothing. An
operation may post more than one entry: a charge and the part paid at once, a
reversal and its replacement. A payment, a discount, a void, and a correction
also carry the latest entry the person saw, and are refused with the new
balance (`AccountChangedMeanwhile`) when a colleague posted since — so
«everything he owes» is never paid against a balance that has changed.

**A charge is a list of lines**, in `account_transaction_items`: a product from
the catalog, or a free line with a name and an amount — «350, miscellaneous» is
one free line. `ChargeLinePricer` reads every product again through the Catalog
module's contract and copies its name, unit, and current price onto the line
(`list_price`) beside the price it was sold at (`unit_price`), refusing a
product archived since it was picked or a price changed since it was seen. A
loose product is sold by weight or by amount («بـ10 جنيه»), its weight following
from its price. Each line is rounded to the minor unit on its own, so the total
is the sum of what the screen shows.

**The account keeps a summary of its ledger** beside `outstanding`: the latest
entry (also the version a screen compares), its time, the latest payment still
standing, and since when the balance has been owed without falling to zero —
all written with the entry. `ReconcileAccountTotals`, a step of `app:sync`,
recomputes them from the entries and puts a stale one back; an entry whose
balance does not follow from the one before it is reported and refused a fix,
because the entries are the record and only a person can find out what changed
them.

An account is opened by `OpenAccount` — when a customer is added, and again on
first posting, so "every customer has an account" does not depend on a listener
never failing.

Other modules read an account through `AccountRepositoryInterface` —
`findByCustomer()`, and `findManyByCustomers()` for many at once, each an
`AccountStanding` — and write only through
`App\Modules\Accounts\Contracts\AccountLedgerInterface`: `charge()`,
`payment()`, and `void()`, each returning the operation as posted, with the
balance on either side of every entry. Opening balances, discounts, and
corrections are this module's own screens' actions. `AccountStanding::wouldBreachLimit()` is the one statement of the credit-limit
rule: the ledger refuses with it, and a caller asking first gets the same answer.
Refusals come back as exceptions from `Contracts/Exceptions/`, carrying the
facts — customer id, limit, attempted total, the balance now — not the account
record.

**Who may correct what** is `AccountTransactionPolicy`: whoever recorded an
entry, for `accounts.correction_window_minutes` (fifteen) after, and anyone
holding `accounts.correct` at any time. Opening balances, discounts, and credit
limits take `accounts.adjust`.

### Decided, and being built

The customer-account workspace and the catalog it sells from were designed
before any of it was written, and every choice below was confirmed by the
owner on 2 October 2026. The full design — screens, flows, edge cases, the
starter catalog — is in two documents:
[customer accounts](https://claude.ai/artifact/2zjyYn47vAZp2Y1Ahiuo7C) and
[the catalog and the product picker](https://claude.ai/artifact/7WAqGdDhS5eZN9aVmAs7N8).
This section is the part the code must keep to; the names below become real
as each slice lands, and this list shrinks into the sections above.

**The lines of a charge live in Accounts**, because a charge is what happens
on a customer's tab; a cash-sales module, if one is built, posts its on-account
sales through `AccountLedgerInterface::charge()` like any other caller.

**A long number in the picker will be a barcode**, once a scanner is used:
the column is there, and the picker already treats eight digits or more as
something other than an amount.

---

## 7b. The catalog

**A product is one thing on the shelf at one price.** Each pack size is its own
product — «أرز الضحى 1 كجم» and «أرز الضحى 5 كجم» are two — the way each has its
own barcode, and a search for «رز 5» lands on one without a second step. A
product sits in exactly one category, at most two levels deep, so the picker's
category tiles and a report by category each have one answer.

**It is sold by a unit**, `App\Modules\Catalog\Contracts\Enums\SaleUnit`: the
piece, the kilo, or the litre. Its price is per unit. Quantities, wherever they
are stored, are integers in thousandths of the unit — two pieces are 2000, a
quarter kilo 250 — the same discipline as money, for the same reason.

**It is found by what the counter calls it.** `search_terms` holds the other
names («بامبرز», «chipsy», «كوكا»), and `search_name` — the name and those names,
folded by `SearchText` — is written on every save and by nothing else. A
barcode column is there, empty, so a scanner needs no migration.

Other modules read products through
`App\Modules\Catalog\Contracts\ProductRepositoryInterface` — one, many at once
(archived ones included, since a sale may name one archived meanwhile), the
active catalog in shelf order, and a version that changes with any product or
category, so a screen holding a copy can ask whether it is stale — and
categories through `App\Modules\Catalog\Contracts\CategoryRepositoryInterface`.
They copy what they sell onto their own records; nothing outside the module
changes a product.

**A shop starts from a catalog**: `php artisan app:catalog:install-starter`
adds a general grocery's 20 shelves and 175 products from a file in the module.
Each record's key is its slug, and a key already present is left exactly as the
owner left it — a corrected price, a new name, an archived product — so the
command is safe on a shop that already sells and safe to run again. The prices
in the file are samples. Stock is not tracked yet; it will be a ledger of
movements, designed first.

**Kept from two screens**, behind `catalog.view` to see and `catalog.manage`
to change. Products (`/admin/catalog/products`) are found the way the picker
finds them — a name, another name, a barcode or a code — narrowed to a shelf
and the shelves under it, on sale or archived, in shelf order. Prices change
often, so a price is changed where it stands: Enter on the row, the new price,
Enter, and the focus is back on the row for ↓ and the next one
(`resources/js/admin/modules/inline-edit.js`); the status line says what it
was and offers it back, unless someone changed it again since. Nothing
already recorded changes — every line keeps the price it was sold at. The
rest of a product is a drawer: its name, shelf, unit, price, other names, and
codes, a name used once, a barcode and a code once each.

**A product that was sold is archived, never deleted.** The lines of a sale
name it by a foreign key, so the database refuses the deletion whichever
module holds them, and the drawer offers to archive it instead — the catalog
never needs to know who holds it (`DeleteProduct`). An archived product
leaves the picker and comes back only onto a shelf in use. Deleting a starter
product lets `app:catalog:install-starter` bring it back; archiving keeps it
gone.

**Categories** (`/admin/catalog/categories`) are listed in the order the picker
shows their tiles, each sub-shelf after the one above it, moved up and down
among the ones beside it, with an icon from `Category::ICONS`. Two levels at
most: only a top shelf holds others, and one that holds others stays at the
top. Only an empty shelf is archived — one still holding products on sale
would take them out of the picker without a word — and only one holding
nothing at all is deleted.

---

## 7c. Customers and their groups

**Customers is the person, not their money**: who someone is — name, phone,
address, notes, a portal login if the shop gave one — the groups the owner puts
them in, and finding them. What they owe and how they pay is Accounts', worked
out from the ledger; a group is never a status, because a status typed by hand
is wrong a week later.

**A phone number is one person.** It is stored as
`App\Foundation\Text\PhoneNumber` gives it — its digits, in Latin, a leading
`+` kept — so «٠١٠٠ ١٢٣ ٤٥٦٧» and «0100-123-4567» are one number, and a blank one
is none. Adding or changing a customer under a number someone else has is
refused with `PhoneAlreadyUsed`, which names who has it so the screen can open
them instead. The check runs inside the write transaction, which SQLite begins
immediately; the column's unique index is the last word anywhere else.

**Found by any spelling.** `search_name` is the name folded by `SearchText`,
written on every save. Every word of a search must match — in the name, or, for
digits, in the phone — and the best come first: the name itself, a name that
starts with the term, one with a word that does, the rest; a phone that ends in
the digits before one that only has them.

**Groups are the owner's** — «جملة», «قريبين», «موظفين الشركة» — any number per
customer, each one of eight colours
(`App\Modules\Customers\Contracts\Enums\GroupColor`) tuned for light and dark,
an optional icon, and the owner's order. Archiving hides a group and keeps who
is in it; only a group nobody is in can be deleted, and the database refuses
the rest. A screen choosing a customer's groups never shows archived ones, so
saving the choice leaves those as they were.

Other modules read customers through `CustomerRepositoryInterface` — one, many
at once, `search()` for the quick lookup, and `searchIds()` for a list that
pages and sorts by what another module holds — and groups through
`CustomerGroupRepositoryInterface`, a page's customers' groups in one query.
They add or change a customer only through `CustomerDirectoryInterface`:
`add()` announces `CustomerAdded`; neither links a login. Inside the module,
`CreateCustomer` and `UpdateCustomer` are the steps behind it. A cashier may
add a customer (`customers.create`); changing one and keeping the groups is
`customers.manage`.

---

## 7d. The counter's workspace

`/admin/accounts`, behind `accounts.view`: every customer, where each account
stands, and the drawer that records for any of them without opening their
page. Customers has no screens of its own; its customers are worked with here,
through its contracts.

**The list** is `App\Modules\Accounts\Domain\Queries\CustomerAccountList`.
Narrowed by who someone is — the search, a group — Customers answers with ids
first; the accounts are filtered, sorted, and paged on their own table; the
page's names and groups come in one query each, so the page costs the same
handful of queries for five customers or five thousand. Every filter is in the
address (`CustomerAccountFilter`), so a filtered list is a link. It sorts by the
latest entry (whoever stands at the counter was likely served lately), the
largest balance, the oldest debt, or the newest customer — not by name, because
the name is not in the accounts query. One line of totals sums whatever the
list keeps.

**Activity and lateness are worked out, never stored.** «Late» is owing with
no payment for `accounts.late_after_days` (thirty) since the later of the last
payment and the start of the debt; «dormant» is no entry for
`accounts.dormant_after_days` (ninety). Both come from the account's summary
when the list asks, so changing a threshold changes every account at once. A
row warns only when it matters: late, past the limit, or dormant while owing.

**Balances in the counter's words** — «عليه», «له», «خالص», never a minus sign
(`<x-accounts::admin.balance>`), in the colour of its direction — red
owing, green owed (`.flow-debit`, `.flow-credit`), always beside the word. The
same two colours mark «أخذ» and «دفع» wherever they are pressed, and tint a
statement's lines, fuller under the pointer. Four tiles over the list count
who owes, who is owed, who is settled, and who is late, each a press away from
the customers it counts. The owner's groups are neutral chips, each
with a dot in its own colour (`GroupColor`, drawn as the shell's `.tone-*`
pairs); a chip filters the list, and «إدارة المجموعات» makes, edits, archives, and
deletes them behind `customers.manage`, which also adds picked rows to one. A
new customer is a name and a phone (`customers.create`); a phone someone has is
refused with an offer to show them.

**The drawer** records «أخذ» and «دفع». Each opening is one operation id, so a
double press or a retry posts once. Above the save it shows, large, what the
balance becomes. It asks — never refuses — when the same amount of the same
kind was recorded for the same customer within
`accounts.duplicate_warning_seconds`; a payment larger than what is owed asks
whether the rest went back as change or stays as credit; a payment decided on a
balance a colleague changed meanwhile comes back with the new one
(`AccountChangedMeanwhile`). For ten seconds after a save its toast offers to
undo it: a void of each entry, with the reason «undone at once», refused once
someone else has posted since.

**One search box** does the charge. A product's name finds it; a number alone
is an amount with no product to it; a name that finds nothing is a line by that
name; «3*» before a name is a quantity. Enter adds, Ctrl+Enter records — so the
main journey is `+`, «350», Ctrl+Enter. The suggestions are what this customer
took most in ninety days, or what the shop sold most in thirty
(`PopularProducts`, from the lines themselves, voided charges left out), and the
shelves narrow the search to one. A weighed product is weighed — the quantity
field takes the focus, with ¼ ½ ¾ 1 a press away — or sold by amount, its
weight following from its price. A price changed at the counter is marked,
with the catalog's beside it, and both are stored.

**The search runs in the browser.** The drawer hands the active catalog to
`resources/js/admin/modules/search-picker.js` from a `#[Renderless]` method,
with the catalog's version, and afterwards only answers whether the version it
holds is current. The query is folded by `search-text.js`, the twin of
`SearchText`, held to the same fixture by a browser test; every word must be
found, a whole word ranking above the start of one, the start above the
inside, and one letter off for words of four letters or more; letters typed on
an English layout are read again as Arabic («.dj» is «زيت»). The picker knows
nothing of products: it searches what it is given and calls the component's
methods. The browser never decides a price — the ledger prices every line again
from the catalog when it saves.

**Keys by where they are, not what they type** (`shortcuts.js`): `/` searches,
`N` adds a customer, ↑ ↓ move between rows, `+` and `−` open «أخذ» and «دفع» for
the row — on an Arabic layout too, where the slash key types «ظ». Settings →
Appearance turns them off for this browser (`admin.shortcuts_storage_key`,
applied before the first paint by the head script): the hints that name keys
(`.kbd-hint`) go, and only the arrows and Enter in a list still answer. Bootstrap
keeps the dialogs and drawers; `overlays.js` lets a component open and close
them, and a drawer with lines not recorded asks before it closes. A drawer's
backdrop is moved to the body as it appears: Bootstrap puts it beside the
drawer, inside the component, where the next redraw would take it away.

**The quick search**: Ctrl K, or the header's search field, from any screen.
The shell's `App\Admin\Search\AdminSearch` asks every
`App\Admin\Contracts\Search\SearchSourceInterface` the modules name through
`ProvidesAdminSearchInterface`, then its own screens, each group shown only
to whoever holds its permission. Accounts finds customers with the end of
their phone, their groups, and where they stand, with «+» and «−» opening
their account with the drawer ready (`?entry=charge`); before anything is
typed, whoever was served today; when nobody matches, the offer to add them.
Catalog finds products on sale with their shelf and price. The dialog asks
nothing until it is opened.

**The journal** (`/admin/accounts/journal`) is a business day's book: every
entry for every customer, the latest first, the day's figures, and «تسليم
الدرج» — what each person took on account and the cash they collected, which
the drawer is counted against at closing
(`App\Modules\Accounts\Domain\Queries\DailyJournal`). The day follows the
owner's zone and the hour the business day starts; voided entries are listed,
struck through, and counted in nothing.

**The account page** (`/admin/accounts/{customer}`, under the list in the
trail) says who the customer is, where the account stands and for how long,
and the two big buttons; its figures leave voided entries out. The statement is
`App\Modules\Accounts\Domain\Queries\AccountStatement`: newest first, a
business day at a time with where each day ended, fifty entries a page, for a
period (this month, thirty days, three months, everything, or two dates) and a
kind of entry. Nothing is summed from the ledger's start: each entry carries the
balance after it, so a period starts from the one after the entry just before
it. A voided entry stays, struck through and saying so; a void and a
correction read on the day they were made; an entry undone at once reads as
one grey line. Printing it prints the statement alone, headed with the shop,
the customer, and the period.

**An entry's details** open in a drawer: its lines with any price changed at
the counter, the balance either side, who recorded it and when, and what
became of it. From there — behind `AccountTransactionPolicy`, and never for a
void — it is voided with a reason, corrected in the recording drawer (filled
with what it was, asking why, posting the reversal and the replacement as one),
or moved whole to the customer it belonged to; a product no longer for sale
moves as a line by its name and total (`EntryLines`). What may not be done is
shown disabled with the reason, so nobody thinks the feature missing. Each of
these carries the balance the person saw, and is turned back if a colleague
posted since. Opening balances, discounts, and the credit limit are a dialog
behind `accounts.adjust`; the customer's details and groups, behind
`customers.manage`, go through Customers' directory.

**The drawer is on every screen.** Accounts names it through
`ProvidesAdminOverlaysInterface`, so the layout keeps it, closed, for whoever
may post; the list and the account page open it for their rows, and any other
screen — a module's included — opens it filled in with an
`App\Modules\Accounts\Contracts\Data\EntryOpening`: whose account, «أخذ» or
«دفع», an amount and what it was for, a note, and the operation id the save
will carry. Opening posts nothing; the person checks and saves. The ledger's
`AccountEntryPosted` names that operation, and the entry a correction
replaces, so whoever opened the drawer knows its own entry when it arrives.

**To try it**, `php artisan app:accounts:demo` adds forty made-up customers —
three of them «محمد أحمد» — in four groups, with three months of entries posted
through the real ledger with the clock set back: a late payer, a dormant one,
one past the limit, one in credit, voided and corrected entries. It refuses in
production and refuses a second run.

---

## 7e. The notebook

When the shop is full there is no time to open an account. «الكشكول» —
`App\Modules\Notebook` — is for that: a button at the bottom of every screen,
or the K key, opens one line to jot «أم كريم زيت وسكر 75», or a microphone to
say it. A note is not money: it moves no balance, no report counts it, and it
posts nothing by itself.

When it is quiet, the notebook is gone through (`/admin/notebook`), oldest
first. Each note is read for who it seems to be about, how much, and what for
— by `App\Modules\Notebook\Contracts\NoteReaderInterface`, bound to a reader
of rules: the customers' own search over the first words or the last, the
last figure in either script. It is a contract so a smarter reader — a small
model that also hears voice notes — can replace it, and whatever reads only
suggests. Choosing the customer opens Accounts' drawer with an `EntryOpening`
carrying the note's operation id; when the ledger announces that operation,
the note is posted. Voided — undone at once, or later — it waits again with a
new operation id, so its next posting is never mistaken for the old one; a
correction carries it to the replacing entry. A note that was nothing is
deleted, and its clip with it.

A voice note is recorded in the browser (`resources/js/admin/modules/voice-recorder.js`,
which knows nothing of notebooks) and kept on the private disk under
`notebook/`; uninstalling the module removes them. The microphone needs a
secure page: `localhost`, or the site over HTTPS — a phone on the shop's
network reaching the computer by its address gets the line, not the
microphone, until the site has a certificate.

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
| `app:catalog:install-starter` | adds the starter catalog's shelves and products that are not there yet — see §7b | `app/Modules/Catalog/Console/` |
| `app:accounts:demo` | adds made-up customers with three months of entries, to try the screens on; never in production — see §7d | `app/Modules/Accounts/Console/` |

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

A step that cannot fix what it found throws, saying what stopped it, and
`app:sync` marks that step FAIL, prints the reason, and runs the others. The
caches step learned this the hard way: Laravel's `view:clear` reports success
even when Windows refuses to delete a compiled view an administrator's terminal
wrote, and the step used to answer DONE over a fix that had done nothing.

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
| `config/foundation.php` | what every product reads: version, languages, currency, and the local time zone and day start an installation begins with (the owner changes those from the panel — §6a) |
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
- **The change log covers the business, not access.** Granting a role
  (`app:admin:grant`) and signing in are not in it yet.
- **A customer without a login has no language.** `users.locale` covers logins;
  a statement sent to someone who only exists in the books needs the language
  on their customer record. Due with the first message sent to a customer.
- **The site's language is not in its URLs.** One address serves both
  languages, so a search engine indexes one and a shared link opens in the
  reader's language, not the sender's. Due before the first public page that
  should be found by search — the URL is the expensive thing to change later.

Not built yet: suppliers, purchases, cash sales, and stock, each designed
first the same way (§7, *Decided, and being built*).
