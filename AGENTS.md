<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- livewire/flux (FLUXUI_FREE) - v2
- livewire/livewire (LIVEWIRE) - v4
- larastan/larastan (LARASTAN) - v3
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== livewire/core rules ===

# Livewire

- Livewire allow to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

</laravel-boost-guidelines>
<project-guidelines>
=== project/base rules ===

# Project Rules

These are the rules. The reasons are in [`ARCHITECTURE.md`](ARCHITECTURE.md) —
read it before writing code. Read [`docs/adding-a-module.md`](docs/adding-a-module.md)
before adding a module, [`docs/adminlte.md`](docs/adminlte.md) before
touching admin markup, and the [`ui-ux-development`](.ai/skills/ui-ux-development/SKILL.md)
skill before building or reviewing any screen. Every rule below is enforced
by a test unless it says otherwise; if a change needs a test to change,
change it deliberately.

## The three layers

```
Modules      what the system is about   →  app/Modules/{Name}/
Areas        who is looking at it       →  app/Admin/  ·  app/Frontend/
Foundation   how anyone reaches it      →  app/Foundation/
```

- **Arrows point down only, with no exemptions.** The foundation never names a
  shell or a module. A shell never names a module — not its classes, not its
  `{key}::` views or strings. `config/` is the only place that names everything.
- To place a file, ask: *is it about the shop's business — customers, products,
  debts, sales?* Yes → that business's module. Is it about running the
  application — signing in, layouts, language, money as a type, permissions as
  machinery? → foundation (for both areas) or a shell (for one).
- Each layer has its own provider: `App\Foundation\FoundationServiceProvider`,
  `App\Admin\AdminServiceProvider`, `App\Frontend\FrontendServiceProvider`.
  There is no `app/Providers`, no `App\Models`, no `app/Http`, no `app/Support`.
- Every interface name ends in `Interface`, and lives in its layer's
  `Contracts/`: `app/Foundation/Contracts/`, `app/{Admin,Frontend}/Contracts/`,
  a module's `Contracts/`.
- **The product's name is never written into code.** It is a value —
  `APP_NAME`, and the `shell.brand.name` string — so renaming the shop is
  changing two values. No command, config file or key, translation key,
  helper, cookie, env variable, CSS name, or class in the foundation or the
  shells spells it — a test fails otherwise. Wording about the business in the
  shell's strings is fine; the name itself is not.

## Modules

- A module is a Laravel service provider extending
  `App\Foundation\Modules\ModuleServiceProvider`, enabled by one line in
  `config/modules.php`; Laravel creates it. Bind with `$bindings`, listen with
  `Event::listen()` in `boot()`, offer commands from `Console/` with
  `$this->commands()`. `register()` is final — put the module's own registrations in
  `registerModule()`.
- The provider is the one place that says what the module brings. **Declare**
  what may or may not exist: `protected ?string $config = 'Config/{key}.php'`
  and `protected array $routes = ['admin' => 'Areas/Admin/routes.php',
  'frontend' => 'Areas/Frontend/routes.php']`. What always has the same shape
  is convention: `Resources/views/{admin,frontend}/`,
  `Resources/lang/{ar,en}/module.php`, `Database/Migrations`.
  **Nothing checks for a file at runtime** — a test fails if a declared file
  is missing or a present one is undeclared.
- A module's folders: `Contracts/` (public), `Domain/` (business: Actions,
  Services, Listeners), `Database/` (data: Migrations, Models, Repositories,
  Factories), `Areas/{Admin,Frontend}/` (its faces: routes, Controllers,
  Requests), `Console/` (its commands), `View/Components/{Admin,Frontend}/`
  (the classes behind its components that need data). Its views are in `Resources/views/{admin,frontend}/` and work as
  views, `<x-{key}::…>` components, and Livewire screens. **Never put a module's table, string, setting, or fixture in a
  shared file.** The one exception is the names of its environment variables:
  `.env` is one file per installation, so each `env()` a module's
  `Config/{key}.php` reads is listed in `.env.example` — a test fails otherwise.
- A module adds to an area only through that area's interfaces:
  `ProvidesAdminNavigationInterface`, `ProvidesPermissionsInterface`,
  `ProvidesAdminSettingsInterface`. A real dependency is declared with
  `DependsOnModulesInterface`; the graph must stay acyclic, and a missing
  dependency stops the application at boot.
- The shell wraps a module's routes in its key: `/admin/{key}/…`,
  `admin.{key}.…`. Every screen route carries `can:{permission}`, the same
  permission its sidebar item names.
- **Modules stay declarations.** No queries while registering or booting —
  `RenderCostTest` fails otherwise — and no file reads, which no test can see:
  keep that one in review.

## Between modules

- **`Contracts/` is a module's only public folder.** It holds the interfaces and
  every type they mention: `Data/` (readonly DTOs), `Enums/`, `Events/`,
  `Exceptions/`. `Domain/` is private.
- **Never hand another module an Eloquent model**, and never relate models
  across modules — no relation methods, and **no `resolveRelationUsing()`
  anywhere**. Hold the other module's id; read through its repository. A DTO is
  built by the owning model (`$customer->toData()`), so `Contracts/` never
  imports from `Domain/`.
- A module's data → its **`{Entity}RepositoryInterface`**, an ordinary
  repository: `find`, `findMany` (a list is one query), `save`, `delete`, DTOs
  in and out, and **no rule and no event in it**. It offers only what its data
  permits: the accounts repository never writes what a customer owes and has
  no `delete`. An operation with rules → a named service contract
  (`AccountLedgerInterface::post()`); a business step like `CreateCustomer`
  saves through the repository and then announces. Announcing something → an
  event from `Contracts/Events/`.
- The repository is the boundary for *other* modules. Inside its own module,
  use the Eloquent models directly when the work is local and clear — a locked
  update in a transaction, a query for one screen. Never route a
  check-then-write through a repository's `find()` and `save()`: that is a race.
- Removing a module from `config/modules.php` **disables** it; its tables, rows,
  and permission records stay. Say "disable", never "remove", unless the data
  goes too. `php artisan app:module:uninstall {key}` removes the data, and
  refuses while the module is enabled or anything depending on it is still
  installed.
- A module that keeps anything outside its own tables (stored files, a remote
  account, the logins Customers hands out) implements `UninstallableInterface`:
  `leftovers()` describes what is there now, `uninstall()` removes it. Both run
  only while its tables exist, so both may read them. A layer that holds
  something for every module contributes an `UninstallStepInterface`, tagged in
  its provider.
- Every published event implements `ShouldDispatchAfterCommit`.
- The one allowance: a module's `Database/` fixtures may use a declared
  dependency's `Database/` factories.

## Areas and identity

- `admin` and `frontend` are two applications sharing a database. A file
  belongs to exactly one area: routes, controllers, middleware, views, layouts,
  CSS, and JS. No page loads both areas' bundles.
- **Never test a URL prefix by hand** — ask `App\Foundation\Area\Area`.
- The areas are separated three times over: **separate session cookies**
  (`BindAreaSession`), **separate guards** (`admin` / `web`, backed by the
  scoped `AdminUser` / `FrontendUser`), and **the area check** (`admin` /
  `frontend` middleware). All three are persistent Livewire middleware; any new
  area middleware must be too.
- A middleware that refuses a Livewire update must **throw** its response
  (`HttpResponseException`, `abort()`), never return it — Livewire drops any
  returned response that is not a redirect.
- Route middleware is `auth:admin` / `auth:web`, never bare `auth`. Admin code
  asks `Area::Admin->user()`, never `Auth::user()` — the default guard is the
  customer's. Admin screens use `admin.password.confirm`, never `password.confirm`.
- Anything area-specific in a session gets an area-specific key outside
  Laravel's `auth.*` namespace.
- A login signed in to one area is a guest in the other — the other area's
  guard cannot even see it — and is sent to that area's sign-in screen. Should
  a login ever reach the wrong guard, the area middleware answers 403.
- In tests, sign in with `actingAsAdmin()` / `actingAsCustomer()`;
  `actingAs($user)` alone uses the frontend guard.

## Logins and customers

- `users` is logins; `users.area` is the one area a login opens, cast to
  `Area`. **`area` is never fillable** — it is set by `AdminUser` /
  `FrontendUser`, and the column defaults to `frontend`.
- `customers` is the shop's record of a person, with a nullable `user_id`.
  **Never assume a customer has a login.**
- **There is no public sign-up, and none may be added.** `CreateCustomer` adds
  someone to the books, and only ever links a `FrontendUser`;
  `GrantPortalAccess` hands them a login.
- Screen code (`app/{Admin,Frontend}/Http`, `resources/views`) never reads logins
  through the base `User` — only through its area's model. Administrators
  come from `php artisan app:admin:grant`, which refuses a customer's login.
- Every auth route is in `routes/auth.php` (`Fortify::ignoreRoutes()`). Fortify
  serves the frontend only; the admin area signs in through
  `App\Admin\Http\Controllers\Auth`.
- `AdminUser` and `FrontendUser` subclass `User` over one table. Anything
  Laravel derives from a class name (a foreign key, a morph class, a guard
  name) is pinned on the model and covered by `GuardModelConsistencyTest` —
  add any new one there.

## Authorization

- Permissions are enums, one per module, implementing
  `App\Admin\Contracts\Authorization\PermissionInterface`, prefixed with the module key.
  Never a loose string in code.
- Roles are configuration, in `config/roles.php`, as patterns over permission
  names. Never name a module's permission in shell code to define a role.
- The unrestricted role is granted every check by `Gate::before`.
- Customers have no roles or permissions.

## Admin shell

- `AdminNavigation` is the one source for the sidebar, breadcrumbs, content
  header, and tab title. **Never write a breadcrumb by hand.** A screen with its
  own internal navigation passes `:breadcrumbs="false"`.
- **`#[Scoped]` where there is a reason**: every repository and service a
  module publishes, and every object that keeps state for the request (the
  shell's registries). Actions and listeners are plain classes. Never
  `#[Singleton]` or `$singletons` — they outlive the request. A per-request
  object may memoise. Add new shared objects to `RenderCostTest`.
- An unbuilt screen is a normal route to `PlannedSectionController`, with
  `->defaults('description', '{key}::module.planned.{screen}')`. There is no
  "planned" flag.
- Do not use `Route::view()` in the admin area; use a controller.
- A shell screen's controller is in `app/{Admin,Frontend}/Http/Controllers`; a
  business screen's is in `app/Modules/{Name}/Areas/{Admin,Frontend}/Controllers`.
  The foundation has no endpoints.

## Views and assets

- `resources/views/admin/` is `admin::`, `resources/views/frontend/` is
  `frontend::`. There is no third namespace; `resources/views/flux/` holds only
  Flux overrides.
- The frontend is the whole public face: the site at `/`, sign-in, and the
  signed-in visitor's pages under `/account`. Its routes are named
  `frontend.*`; a module's are `frontend.{key}.…` at `/{key}`, public unless
  the route adds `['auth:web', 'verified', 'frontend']` itself.
- Screens go under `{area}/page/`; components under `{area}/{layout,ui,form,table}/`.
  Repeated markup becomes a component before it appears a third time.
- **A template renders; it never fetches.** No container (`app(…)`,
  `resolve()`, `@inject`), no signed-in user (`auth()`, `Auth::`, `@auth`), no
  `request()`, no `session()`, and no PHP class (`Route::`, `\App\…`) in
  Blade; `__()`, `route()`, `config()`, `old()` are fine. A component that needs
  data has a class — the shells' in `app/{Admin,Frontend}/View/Components/`
  (`<x-admin::layout.sidebar>` is `Layout\Sidebar`), a module's in its
  `View/Components/` (`<x-{key}::admin.card>` is `View\Components\Admin\Card`).
  A screen gets its data from its controller, its Livewire component, or — for
  a Fortify screen — the view closure in `FrontendServiceProvider`.
- A full-page Livewire component renders inside its `#[Layout]`; its view holds
  only the page body. Pass title and breadcrumbs with `$view->layoutData()` from
  `rendering()`.
- **No `<style>`, no `style=`, no `:style=`, no `<script>` in Blade**, except the
  colour-mode script. Styling goes in `resources/css/admin/_theme.css` as
  classes; behaviour in `resources/js/{area}/modules/`.
- `BASE/TEMPLATES/adminLTE-v4.0.0` is read-only reference. AdminLTE comes from
  npm through Vite — never a CDN tag, never copied CSS or JS. Use its
  `data-lte-*` plugins instead of writing JS.

## Console

- Every command is in its layer's `Console/` folder, registered by that layer's
  provider, and named `app:{group}:{action}`: the foundation's
  `app/Foundation/Console/{Group}/` → `app:{group}:…`
  (`Console/Modules` → `app:module:…`), a shell's `app/{Admin,Frontend}/Console/`
  → `app:admin:…`, a module's `Console/` → `app:{key}:…`. A foundation command
  about the whole installation sits in `app/Foundation/Console/` itself, as
  `app:{action}` (`app:sync`). A command nothing registers fails the build.
- A layer shows its state in Laravel's `php artisan about`, as a section it
  adds itself (`AboutCommand::add()`), never in a command of its own.
- A layer that stores something that can fall behind the code (tables,
  permissions, caches) contributes a `SyncStepInterface`, tagged in its
  provider, for `app:sync`: `findings()` only looks, `fix()` runs
  only after the command is told yes. Its classes are its own — never the ones
  that remove the same data on uninstall. A seeder that stores the same data
  calls the same steps.

## Money and the ledger

- Money is an integer count of minor units: `bigInteger` columns,
  `App\Foundation\Money\Money`, `MoneyCast`. Never `decimal`, never `float`,
  never arithmetic operators on an amount. Parse input with
  `Money::fromDecimalString()`; display with `<x-admin::ui.money>`.
- `customer_accounts.outstanding` is what the customer owes. It is written only
  by `PostAccountTransaction`, is never fillable, and ledger rows are never
  edited — correct a mistake by posting its inverse. `Debit` («عليه») raises it,
  `Credit` («له») lowers it.
- Aggregate in the database, not in PHP.

## Language and configuration

- Arabic and English are both first-class. **Every** user-facing string is a
  translation key, added to `ar` and `en` in the same change: the foundation's
  in `lang/{ar,en}/foundation.php`, the shells' in `lang/{ar,en}/shell.php`, a
  module's in its own `module.php`.
- Direction comes from `text_direction()` / `is_rtl()`; directional
  icons flip, digits stay `dir="ltr"`.
- The reader's language is `LocalePreference`, kept per area — in the area's
  own cookie (`Area::localeCookie()`) and on the signed-in login
  (`users.locale`) — never in the session, and never shared between areas.
- Settings: each layer's in its own file — `config/foundation.php`,
  `config/admin.php`, `config/frontend.php` — roles in `config/roles.php`, a
  module's in its `Config/{key}.php`. Never call `env()` outside a config file.

## Testing

- Every change ships with a test. **Every routed screen has a test that renders
  it.** A new screen joins `adminScreens()` or `customerScreens()` in
  `tests/Pest.php`; the render, accessibility, and browser tests then cover it.
- `tests/Browser` runs each screen in a real browser — Arabic and English,
  light and dark, and a 375px phone — through axe. It needs Playwright and its
  Chromium once per machine. If a browser test fails with
  `PlaywrightNotInstalledException`, install them yourself and run it again —
  `npm install` (Playwright is already in `package.json`), then
  `npx playwright install chromium` — rather than skipping the browser tests.
  They read the built assets: run `npm run build` after changing JS or CSS
  when no `public/hot` file shows a Vite dev server running.
- **Never run the tests while the configuration is cached** — a cached
  configuration ignores `phpunit.xml`, and the suite then wipes the real
  database. `tests/TestCase.php` refuses to start outside the `testing`
  environment; if it does, run `php artisan optimize:clear` and say so.
- The feature suite calls `withoutVite()` globally; call `$this->withVite()` in
  any test about what a page loads, or it passes against an empty string.
- A claim about a module's removal, a boot cost, or route caching is proven on a
  real application — see `applicationWithModules()` in `tests/Pest.php`.
- Before finishing, run `vendor/bin/pint --format agent`, `vendor/bin/phpstan
  analyse`, and `php artisan test --compact`. All three must be clean.
- The docs are tested too: a class or path named in them that does not exist
  fails the build. Update the docs in the same change as the code.

</project-guidelines>
