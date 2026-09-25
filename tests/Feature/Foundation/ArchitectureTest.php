<?php

use App\Admin\Authorization\PermissionRegistry;
use App\Admin\Contracts\Authorization\ProvidesPermissionsInterface;
use App\Admin\Contracts\Navigation\ProvidesAdminNavigationInterface;
use App\Admin\Navigation\AdminNavigation;
use App\Foundation\Area\Area;
use App\Foundation\Contracts\Modules\DependsOnModulesInterface;
use App\Foundation\Modules\ModuleRegistry;
use App\Foundation\Modules\ModuleServiceProvider;
use App\Modules\Catalog\CatalogServiceProvider;
use App\Modules\Purchases\PurchasesServiceProvider;
use App\Modules\Sales\SalesServiceProvider;
use Illuminate\Console\Command;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Every PHP file under a directory, relative to the project root.
 *
 * A missing directory fails unless it is optional: a rule reading a folder
 * that was renamed away passes against nothing — how the layer rules once
 * went on checking `app/Customer` long after it became `app/Frontend`.
 *
 * @return list<string>
 */
function sourceFilesIn(string $directory, bool $optional = false): array
{
    $root = base_path($directory);

    if (! is_dir($root)) {
        if (! $optional) {
            throw new RuntimeException("[{$directory}] does not exist; a rule reading it checks nothing.");
        }

        return [];
    }

    $files = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}

function relativePath(string $file): string
{
    return Str::after($file, base_path().'/');
}

/**
 * The folder a module lives in, e.g. `app/Modules/Accounts`.
 */
function moduleRoot(ModuleServiceProvider $module): string
{
    return relativePath($module->path());
}

/**
 * The class a PHP file under `app/` declares, from its path.
 */
function classInFile(string $file): string
{
    return 'App\\'.str_replace(['/', '.php'], ['\\', ''], Str::after($file, app_path().'/'));
}

/*
|--------------------------------------------------------------------------
| The three layers
|--------------------------------------------------------------------------
|
| Modules → Areas → Foundation. Arrows point down only, and no rule here has
| an exemption: an exemption is how the last design hid the foundation
| depending on the admin shell.
|
*/

test('the foundation never names a module', function () {
    $offenders = array_filter(
        sourceFilesIn('app/Foundation'),
        fn (string $file) => str_contains((string) file_get_contents($file), 'App\\Modules\\'),
    );

    expect(array_map('relativePath', array_values($offenders)))->toBe([]);
});

test('the foundation never names an area shell', function () {
    // The shells are built on the foundation. Foundation → Admin would mean
    // the foundation cannot exist without that shell.
    $offenders = array_filter(
        sourceFilesIn('app/Foundation'),
        fn (string $file) => preg_match('/App\\\\(Admin|Frontend)\\\\/', (string) file_get_contents($file)) === 1,
    );

    expect(array_map('relativePath', array_values($offenders)))->toBe([]);
});

test('neither shell names a module', function () {
    // The shells ask the registry what is running. Naming a module — its
    // classes, or its view and translation namespace — is how a shell stops
    // surviving the module's removal.
    $keys = app(ModuleRegistry::class)->all()->keys()->implode('|');
    $offenders = [];

    $files = [
        ...sourceFilesIn('app/Admin'),
        ...sourceFilesIn('app/Frontend'),
        ...sourceFilesIn('routes'),
        ...sourceFilesIn('database/seeders'),
        ...sourceFilesIn('resources/views/admin'),
        ...sourceFilesIn('resources/views/frontend'),
    ];

    foreach ($files as $file) {
        $contents = (string) file_get_contents($file);

        if (str_contains($contents, 'App\\Modules\\') || preg_match("/['\"]({$keys})::/", $contents) === 1) {
            $offenders[] = relativePath($file);
        }
    }

    expect($offenders)->toBe([]);
});

test('the foundation and the shells never carry the product\'s name', function () {
    // They are what a school or a clinic starts from. The product's name is a
    // value it sets — APP_NAME, and the `shell.brand.name` string — never a
    // command, a setting, a key, a helper, a cookie, a class, or a file name:
    // a school would otherwise ship `grocery:` commands and a grocery config.
    // The name in every language it is written in.
    $names = array_values(array_unique(array_map(
        fn (string $file) => (string) (require $file)['brand']['name'],
        glob(lang_path('*/shell.php')) ?: [],
    )));

    expect($names)->not->toBeEmpty()->not->toContain('');

    $carries = function (string $text) use ($names): bool {
        foreach ($names as $name) {
            if (mb_stripos($text, $name) !== false) {
                return true;
            }
        }

        return false;
    };

    $files = [
        ...sourceFilesIn('app/Foundation'),
        ...sourceFilesIn('app/Admin'),
        ...sourceFilesIn('app/Frontend'),
        ...sourceFilesIn('config'),
        ...sourceFilesIn('routes'),
        ...sourceFilesIn('database'),
        ...sourceFilesIn('resources/views'),
        ...glob(resource_path('{css,js}/*/*.{css,js}'), GLOB_BRACE) ?: [],
        ...glob(resource_path('{css,js}/*/*/*.{css,js}'), GLOB_BRACE) ?: [],
    ];

    $offenders = array_filter(
        $files,
        fn (string $file) => $carries(relativePath($file)) || $carries((string) file_get_contents($file)),
    );

    // Its strings are values; only the files' names are the shells'.
    $offenders = [...$offenders, ...array_filter(glob(lang_path('*/*.php')) ?: [], fn (string $file) => $carries(basename($file)))];

    expect(array_map('relativePath', array_values($offenders)))->toBe([]);
});

test('every interface lives in its layer\'s Contracts folder', function () {
    // What one layer offers another — to implement, or to call — is found in
    // one place per layer, the way Laravel keeps its own in Illuminate\Contracts.
    $offenders = array_filter(
        sourceFilesIn('app'),
        fn (string $file) => preg_match('/^interface\s+\w+/m', (string) file_get_contents($file)) === 1
            && preg_match('#^app/(Foundation|Admin|Frontend|Modules/\w+)/Contracts/#', relativePath($file)) !== 1,
    );

    expect(array_map('relativePath', array_values($offenders)))->toBe([]);
});

/*
|--------------------------------------------------------------------------
| Between modules
|--------------------------------------------------------------------------
*/

test('a module only reaches into modules it declares', function () {
    $offenders = [];

    foreach (app(ModuleRegistry::class)->all() as $key => $module) {
        $folder = basename($module->path());
        $declared = $module instanceof DependsOnModulesInterface ? $module->dependsOn() : [];

        foreach (sourceFilesIn(moduleRoot($module)) as $file) {
            preg_match_all('/App\\\\Modules\\\\([A-Za-z]+)\\\\/', (string) file_get_contents($file), $matches);

            foreach (array_unique($matches[1]) as $referenced) {
                if ($referenced !== $folder && ! in_array(Str::lower($referenced), $declared, true)) {
                    $offenders[] = "{$key} → ".Str::lower($referenced).' (undeclared, in '.basename($file).')';
                }
            }
        }
    }

    expect(array_values(array_unique($offenders)))->toBe([]);
});

test('a module only touches another module\'s Contracts', function () {
    // One folder is public. Everything under `Domain/` — models, actions,
    // services, listeners — is private, with no exception.
    //
    // Test fixtures are the one different case: an account cannot exist
    // without a customer row, so a module's factories may use a declared
    // dependency's factories — fixtures building fixtures, never runtime code
    // reaching a record.
    $offenders = [];

    foreach (app(ModuleRegistry::class)->all() as $key => $module) {
        $folder = basename($module->path());

        foreach (sourceFilesIn(moduleRoot($module)) as $file) {
            $isFixture = str_contains($file, '/Database/Factories/');

            preg_match_all('/App\\\\Modules\\\\([A-Za-z]+)\\\\([A-Za-z]+(?:\\\\Factories)?)\\\\/', (string) file_get_contents($file), $matches, PREG_SET_ORDER);

            foreach ($matches as [, $referenced, $folderWithin]) {
                if ($referenced === $folder || $folderWithin === 'Contracts' || ($isFixture && $folderWithin === 'Database\\Factories')) {
                    continue;
                }

                $offenders[] = "{$key} → {$referenced}\\{$folderWithin} (in ".relativePath($file).')';
            }
        }
    }

    expect(array_values(array_unique($offenders)))->toBe([]);
});

test('a module\'s Contracts never lead back into its private folders', function () {
    // A DTO that knew how to build itself from the model would put the model
    // in the published surface. The model builds the DTO instead.
    $offenders = [];

    foreach (app(ModuleRegistry::class)->all() as $module) {
        foreach (sourceFilesIn(moduleRoot($module).'/Contracts', optional: true) as $file) {
            if (preg_match('/App\\\\Modules\\\\[A-Za-z]+\\\\(Domain|Database)\\\\/', (string) file_get_contents($file)) === 1) {
                $offenders[] = relativePath($file);
            }
        }
    }

    expect($offenders)->toBe([]);
});

test('nothing in a module\'s Contracts takes, returns, or holds a record', function () {
    // Checked on the types themselves, so it covers every model any module
    // will ever have — not a list of names someone has to keep up to date.
    $offenders = [];

    $isRecord = function (?ReflectionType $type) use (&$isRecord): bool {
        if ($type instanceof ReflectionNamedType) {
            return ! $type->isBuiltin() && is_a($type->getName(), Model::class, true);
        }

        if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
            return collect($type->getTypes())->contains(fn (ReflectionType $inner) => $isRecord($inner));
        }

        return false;
    };

    foreach (app(ModuleRegistry::class)->all() as $module) {
        foreach (sourceFilesIn(moduleRoot($module).'/Contracts', optional: true) as $file) {
            $class = new ReflectionClass(classInFile($file));

            foreach ($class->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                if ($isRecord($property->getType())) {
                    $offenders[] = "{$class->getShortName()}::\${$property->getName()}";
                }
            }

            foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getDeclaringClass()->getName() !== $class->getName()) {
                    continue;
                }

                foreach ([$method->getReturnType(), ...array_map(fn ($p) => $p->getType(), $method->getParameters())] as $type) {
                    if ($isRecord($type)) {
                        $offenders[] = "{$class->getShortName()}::{$method->getName()}()";
                    }
                }
            }
        }
    }

    expect(array_values(array_unique($offenders)))->toBe([]);
});

test('no module attaches a relation to another module\'s model at runtime', function () {
    // A relation registered from outside is a closure stored on the model
    // class on every request — fifty of them is fifty closures in memory per
    // request, and a dependency nothing declares. A module that needs another
    // module's data asks that module's repository, by id.
    $offenders = array_filter(
        sourceFilesIn('app'),
        fn (string $file) => str_contains((string) file_get_contents($file), 'resolveRelationUsing'),
    );

    expect(array_map('relativePath', array_values($offenders)))->toBe([]);
});

test('what a module publishes lives for one request, and nothing outlives it', function () {
    // The repositories and services a module publishes are asked for by other
    // modules, often several times in one request, so each is one instance per
    // request (`#[Scoped]`) — free to remember what it fetched without that
    // reaching the next visitor. Actions and listeners hold nothing, so they
    // are plain classes built when needed; scoping them would add a rule
    // without a reason.
    //
    // Nothing is a singleton: under a long-lived worker a singleton outlives
    // the request and carries one visitor's state to the next.
    $unscoped = [];

    foreach (app(ModuleRegistry::class)->all() as $module) {
        foreach ($module->bindings ?? [] as $implementation) {
            if ((new ReflectionClass($implementation))->getAttributes(Scoped::class) === []) {
                $unscoped[] = $implementation;
            }
        }

        expect(property_exists($module, 'singletons'))->toBeFalse("[{$module->key()}] registers singletons.");
    }

    $singletons = array_filter(
        sourceFilesIn('app'),
        fn (string $file) => str_contains((string) file_get_contents($file), 'Attributes\\Singleton'),
    );

    expect($unscoped)->toBe([])
        ->and(array_map('relativePath', array_values($singletons)))->toBe([]);
});

test('a repository only fetches, saves, and deletes', function () {
    // The convention the name carries: `{Entity}RepositoryInterface` is a
    // module's data, stored and fetched, with no rule in it. Anything with
    // rules of its own — the ledger's `post()` — is a service, and says so in
    // its name.
    foreach (app(ModuleRegistry::class)->all() as $module) {
        foreach (sourceFilesIn(moduleRoot($module).'/Contracts', optional: true) as $file) {
            $class = new ReflectionClass(classInFile($file));

            if (! $class->isInterface() || ! str_ends_with($class->getShortName(), 'RepositoryInterface')) {
                continue;
            }

            foreach ($class->getMethods() as $method) {
                expect($method->getName())->toMatch('/^(find|get|search|count|exists|save|delete)/', "[{$class->getShortName()}::{$method->getName()}] is not a data operation — logic belongs in a service.");
            }
        }
    }
});

test('a published contract is resolvable from the container', function () {
    foreach (app(ModuleRegistry::class)->all() as $module) {
        foreach ($module->bindings ?? [] as $contract => $implementation) {
            expect(interface_exists($contract))->toBeTrue("[{$contract}] is not an interface.")
                ->and(Str::startsWith($contract, 'App\\Modules\\'.basename($module->path()).'\\Contracts\\'))
                ->toBeTrue("[{$contract}] is bound but not published in the module's Contracts.")
                ->and(app($contract))->toBeInstanceOf($implementation);
        }
    }
});

test('the module dependency graph has no cycles', function () {
    // Two modules that each need the other cannot be installed independently,
    // and one of them should be listening for the other's event instead.
    $edges = app(ModuleRegistry::class)->all()
        ->map(fn ($module) => $module instanceof DependsOnModulesInterface ? $module->dependsOn() : [])
        ->all();

    $visiting = [];
    $done = [];

    $walk = function (string $node, array $path) use (&$walk, &$visiting, &$done, $edges): void {
        if (isset($done[$node])) {
            return;
        }

        expect($visiting[$node] ?? false)->toBeFalse('Cycle: '.implode(' → ', [...$path, $node]));

        $visiting[$node] = true;

        foreach ($edges[$node] ?? [] as $next) {
            $walk($next, [...$path, $node]);
        }

        $visiting[$node] = false;
        $done[$node] = true;
    };

    foreach (array_keys($edges) as $node) {
        $walk($node, []);
    }
});

test('a module only depends on modules that are installed', function () {
    $registry = app(ModuleRegistry::class);

    foreach ($registry->providing(DependsOnModulesInterface::class) as $module) {
        foreach ($module->dependsOn() as $dependency) {
            expect($registry->has($dependency))->toBeTrue(
                "[{$module->key()}] depends on [{$dependency}], which is not installed.",
            );
        }
    }
});

test('the application refuses to start with a dependency missing', function () {
    // Accounts without Customers fails at boot, by name — not on the first
    // account anyone opens.
    $withoutCustomers = array_values(array_filter(
        config('modules.enabled'),
        fn (string $class) => ! str_ends_with($class, 'CustomersServiceProvider'),
    ));

    expect(fn () => applicationWithModules($withoutCustomers))
        ->toThrow(InvalidArgumentException::class, 'The [accounts] module needs [customers]');
});

/*
|--------------------------------------------------------------------------
| A module's shape
|--------------------------------------------------------------------------
*/

test('every configured module is a module service provider with a stable key', function () {
    $modules = app(ModuleRegistry::class)->all();

    // Configuration files a module key would collide with: the module's
    // defaults are merged under its key, so `mail` would be merged into Laravel's.
    $taken = array_map(fn (string $file) => basename($file, '.php'), glob(config_path('*.php')) ?: []);

    expect($modules)->not->toBeEmpty();

    foreach ($modules as $key => $module) {
        expect($module)->toBeInstanceOf(ModuleServiceProvider::class)
            ->and($key)->toMatch('/^[a-z][a-z0-9]*$/')
            ->and(Str::lower(basename($module->path())))->toBe($key, "[{$key}] lives in a folder named differently.")
            ->and($taken)->not->toContain($key);
    }
});

test('every file a module declares exists, and nothing it has goes undeclared', function () {
    // Nothing is checked for at runtime: the provider says which files it
    // brings, and this is where that is held true — once, at build time,
    // instead of a file check per module on every request.
    foreach (app(ModuleRegistry::class)->all() as $key => $module) {
        $declared = array_filter([$module->configFile(), ...array_map(fn (Area $area) => $module->routesFor($area), Area::cases())]);

        foreach ($declared as $file) {
            expect(is_file($file))->toBeTrue("[{$key}] declares [{$file}], which does not exist.");
        }

        $present = [...glob($module->path('Config/*.php')) ?: [], ...glob($module->path('Areas/*/routes.php')) ?: []];

        foreach ($present as $file) {
            expect(in_array($file, $declared, true))->toBeTrue("[{$key}] has [".relativePath($file).'] but does not declare it.');
        }
    }
});

test('a module\'s permissions are prefixed with its own key', function () {
    foreach (app(ModuleRegistry::class)->providing(ProvidesPermissionsInterface::class) as $key => $module) {
        foreach ($module->permissions() as $permission) {
            expect($permission->value)->toStartWith("{$key}.")
                ->and($permission->group())->toBe($key);
        }
    }
});

test('every capability interface has a consumer', function () {
    // A capability no shell reads is a promise with nothing behind it — a
    // module implementing it would be ignored without a word.
    $consumers = implode("\n", array_map(
        fn (string $file) => (string) file_get_contents($file),
        [...sourceFilesIn('app/Admin'), ...sourceFilesIn('app/Frontend'), ...sourceFilesIn('app/Foundation'), ...sourceFilesIn('routes')],
    ));

    $capabilities = array_filter(
        [...sourceFilesIn('app/Admin'), ...sourceFilesIn('app/Frontend'), ...sourceFilesIn('app/Foundation')],
        fn (string $file) => preg_match('/(Provides\w*|DependsOn\w*|Uninstallable)Interface\.php$/', $file) === 1,
    );

    expect($capabilities)->not->toBeEmpty();

    foreach ($capabilities as $file) {
        $interface = basename($file, '.php');

        expect(str_contains($consumers, "providing({$interface}::class)") || str_contains($consumers, "instanceof {$interface}"))
            ->toBeTrue("[{$interface}] is never read.");
    }
});

test('a module in the sidebar routes every screen it lists', function () {
    foreach (app(ModuleRegistry::class)->providing(ProvidesAdminNavigationInterface::class) as $module) {
        expect($module->routesFor(Area::Admin))->not->toBeNull("[{$module->key()}] is in the sidebar but declares no admin routes.");

        foreach ($module->adminNavigation()->items as $item) {
            foreach ([$item, ...$item->children] as $node) {
                if ($node->route !== null) {
                    expect(Route::has($node->route))->toBeTrue("[{$module->key()}] lists [{$node->route}] but nothing routes it.");
                }
            }
        }
    }
});

test('a module\'s screens live under its own url and name', function () {
    // Set by the shell around the module's routes file, so a module cannot
    // step outside it. This proves the wrapping is still there: an admin
    // screen is `admin.{key}.…` at `/admin/{key}`, a frontend one
    // `frontend.{key}.…` at `/{key}`.
    $registry = app(ModuleRegistry::class);
    $offenders = [];

    foreach (Route::getRoutes()->getRoutesByName() as $name => $route) {
        [$area, $second] = array_pad(explode('.', $name, 3), 2, '');

        if (! in_array($area, ['admin', 'frontend'], true) || ! $registry->has($second)) {
            continue;
        }

        $expected = $area === 'admin' ? "admin/{$second}" : $second;

        if (! str_starts_with($route->uri(), $expected)) {
            $offenders[] = "{$name} is at /{$route->uri()}";
        }
    }

    expect($offenders)->toBe([]);
});

test('no two routes answer the same method and url', function () {
    // The frontend's modules sit at the root of the site, beside the sign-in
    // pages and `/account`. Laravel lets a later route silently replace an
    // earlier one with the same url, so a module keyed `account` or `login`
    // would take over a page without a word — this is the word.
    $seen = [];
    $duplicates = [];

    foreach (Route::getRoutes() as $route) {
        foreach ($route->methods() as $method) {
            $key = $method.' '.$route->uri();

            if (isset($seen[$key])) {
                $duplicates[] = $key;
            }

            $seen[$key] = true;
        }
    }

    expect($duplicates)->toBe([]);
});

test('a module screen is behind the permission its sidebar item names', function () {
    // Hiding a menu entry and closing its URL are one statement.
    foreach (app(ModuleRegistry::class)->providing(ProvidesAdminNavigationInterface::class) as $module) {
        foreach ($module->adminNavigation()->items as $item) {
            if ($item->route === null || $item->permission === null) {
                continue;
            }

            expect(Route::getRoutes()->getByName($item->route)?->gatherMiddleware())
                ->toContain('can:'.$item->permission->value);
        }
    }
});

test('no page redirects merely to reach its own default', function () {
    // `/admin/settings` used to bounce to `/admin/settings/profile`: a URL
    // apologising for itself. An area root is different — `/admin` has no
    // content of its own, and sending it to the dashboard is the normal thing.
    $offenders = [];

    foreach (Route::getRoutes() as $route) {
        if (! str_contains($route->getActionName(), 'RedirectController')) {
            continue;
        }

        $from = trim($route->uri(), '/');
        $target = $route->defaults['destination'] ?? '';

        if ($from !== 'admin' && is_string($target) && str_starts_with(trim($target, '/'), $from.'/')) {
            $offenders[] = "/{$from} → {$target}";
        }
    }

    expect($offenders)->toBe([]);
});

test('modules sharing a heading are merged into one section', function () {
    actingAsAdmin();

    $labels = collect(app(AdminNavigation::class)->sections())->map(fn ($section) => $section->label);

    expect($labels->duplicates())->toBeEmpty();
});

test('every sidebar heading and item has a translation', function () {
    actingAsAdmin();

    foreach (app(AdminNavigation::class)->sections() as $section) {
        expect(__($section->label))->not->toBe($section->label);

        foreach ($section->items as $item) {
            expect($item->title())->not->toBe($item->label);
        }
    }
});

/*
|--------------------------------------------------------------------------
| Disabling a module
|--------------------------------------------------------------------------
|
| The claim the whole design rests on, proven on a real application booted
| without the Catalog module — not on a registry built by hand. This is
| disabling, not uninstalling: the module's code stops loading, and its tables
| and rows are untouched, which is what a config edit should do.
|
*/

test('disabling a module stops everything it registers', function () {
    // Catalog leaves with the modules that need it — Purchases and Sales
    // declare it, and the application refuses to boot without it otherwise.
    $leaving = [CatalogServiceProvider::class, PurchasesServiceProvider::class, SalesServiceProvider::class];
    $without = array_values(array_diff(config('modules.enabled'), $leaving));

    $app = applicationWithModules($without);

    $routes = collect($app['router']->getRoutes()->getRoutesByName())->keys();
    $permissions = $app->make(PermissionRegistry::class)->values();

    expect($app->make(ModuleRegistry::class)->has('catalog'))->toBeFalse()
        // Its screens.
        ->and($routes->filter(fn (string $name) => str_starts_with($name, 'admin.catalog.')))->toBeEmpty()
        // Its permissions.
        ->and(array_filter($permissions, fn (string $permission) => str_starts_with($permission, 'catalog.')))->toBe([])
        // Its views and strings.
        ->and($app['view']->getFinder()->getHints())->not->toHaveKey('catalog')
        ->and($app['translator']->get('catalog::module.name'))->toBe('catalog::module.name')
        // Its configuration.
        ->and($app['config']->get('catalog'))->toBeNull()
        // Its tables.
        ->and(collect($app['migrator']->paths())->filter(fn (string $path) => str_contains($path, '/Modules/Catalog/')))->toBeEmpty();

    // And the rest of the product is still there.
    expect($routes)->toContain('admin.customers.index')
        ->and($permissions)->toContain('customers.view');
});

/*
|--------------------------------------------------------------------------
| The console
|--------------------------------------------------------------------------
|
| A command is how a developer learns from a shell what the system can do,
| so none may hide: `php artisan list app` shows every one, grouped by
| what it acts on.
|
*/

test('every command lives in its layer\'s Console folder, named for its group', function () {
    // `app:module:*` is app/Foundation/Console/Modules, `app:admin:*` the
    // admin shell's Console, `app:{key}:*` a module's — the same prefixing its
    // permissions and routes follow. A foundation command about the whole
    // installation sits in Console itself, as `app:{name}`: `app:sync`.
    $offenders = [];

    foreach (Artisan::all() as $name => $command) {
        $class = $command::class;

        if (! str_starts_with($class, 'App\\')) {
            continue;
        }

        $expected = match (true) {
            preg_match('/^App\\\\Foundation\\\\Console\\\\(\w+)\\\\/', $class, $folder) === 1 => '/^app:'.Str::singular(Str::kebab($folder[1])).':[a-z-]+$/',
            preg_match('/^App\\\\Foundation\\\\Console\\\\\w+$/', $class) === 1 => '/^app:[a-z-]+$/',
            preg_match('/^App\\\\(Admin|Frontend)\\\\Console\\\\/', $class, $shell) === 1 => '/^app:'.Str::lower($shell[1]).':[a-z-]+$/',
            preg_match('/^App\\\\Modules\\\\(\w+)\\\\Console\\\\/', $class, $module) === 1 => '/^app:'.Str::lower($module[1]).':[a-z-]+$/',
            default => null,
        };

        if ($expected === null || preg_match($expected, $name) !== 1) {
            $offenders[] = "{$class} is [{$name}]";
        }
    }

    expect($offenders)->toBe([]);
});

test('every command class is registered', function () {
    // The other way to hide one: a command nothing registers is a file
    // somebody has to stumble on.
    $registered = array_map(fn (object $command) => $command::class, array_values(Artisan::all()));

    $classes = array_map('classInFile', array_filter(
        sourceFilesIn('app'),
        fn (string $file) => is_subclass_of(classInFile($file), Command::class),
    ));

    expect(array_values(array_diff($classes, $registered)))->toBe([]);
});
