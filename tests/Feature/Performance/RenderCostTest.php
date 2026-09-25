<?php

use App\Admin\Authorization\PermissionRegistry;
use App\Admin\Authorization\RoleDefinitions;
use App\Admin\Navigation\AdminNavigation;
use App\Admin\Settings\AdminSettings;
use App\Foundation\Modules\ModuleRegistry;
use App\Foundation\Money\Money;
use App\Modules\Accounts\Database\Models\AccountTransaction;
use App\Modules\Accounts\Database\Models\CustomerAccount;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

uses(LazilyRefreshDatabase::class);

/**
 * Count how many authorization checks a request performs.
 */
function gateChecksDuring(callable $work): int
{
    $checks = 0;

    Gate::after(function () use (&$checks) {
        $checks++;
    });

    $work();

    return $checks;
}

test('the navigation tree is built once per request, not once per reader', function () {
    actingAsAdmin();

    // The sidebar, the content header, and the breadcrumbs all read it. Before
    // this was memoised, drawing one screen cost three full rebuilds and
    // thirty permission checks.
    $navigable = collect(app(AdminNavigation::class)->sections())
        ->flatMap(fn ($section) => $section->items)
        ->filter(fn ($item) => $item->permission !== null)
        ->count();

    $checks = gateChecksDuring(fn () => $this->get(route('admin.dashboard'))->assertOk());

    expect($checks)->toBeLessThanOrEqual($navigable);
});

test('shared objects are one instance per request, and a new one the next', function (string $abstract) {
    // Within a request every caller gets the same object; `forgetScopedInstances()`
    // is what the framework calls between requests under a long-lived worker,
    // and after it the object is new. A singleton would pass the first half of
    // this and fail the second — carrying one visitor's state to the next.
    $first = app($abstract);

    expect(app($abstract))->toBe($first);

    app()->forgetScopedInstances();

    expect(app($abstract))->not->toBe($first);
})->with(function (): array {
    $shell = [AdminNavigation::class, AdminSettings::class, PermissionRegistry::class, RoleDefinitions::class];

    // Every contract a module publishes — read from the providers' declared
    // bindings, because a dataset is resolved before the application boots.
    $published = collect((require dirname(__DIR__, 3).'/config/modules.php')['enabled'])
        ->flatMap(fn (string $module) => array_keys((new ReflectionClass($module))->getDefaultProperties()['bindings'] ?? []))
        ->all();

    return [...$shell, ...$published];
});

test('rendering an admin screen stays within a small query budget', function () {
    actingAsAdmin();

    DB::enableQueryLog();
    $this->get(route('admin.dashboard'))->assertOk();

    // A budget, not a target: this fails loudly if a screen starts issuing a
    // query per navigation item.
    expect(DB::getQueryLog())->toHaveCount(4);
});

test('the outstanding total is summed in the database, not loaded into memory', function () {
    $account = CustomerAccount::factory()->create();

    AccountTransaction::factory()->count(40)->for($account, 'account')->debit()->create([
        'amount' => Money::fromDecimalString('1.00'),
    ]);
    AccountTransaction::factory()->count(10)->for($account, 'account')->credit()->create([
        'amount' => Money::fromDecimalString('2.00'),
    ]);

    DB::enableQueryLog();
    $total = $account->calculatedOutstanding();

    expect($total->toDecimalString())->toBe('20.00')
        // One aggregate, however long the ledger is.
        ->and(DB::getQueryLog())->toHaveCount(1);
});

test('the outstanding total stays exact over a long ledger', function () {
    $account = CustomerAccount::factory()->create();

    // Amounts a float would not represent exactly.
    AccountTransaction::factory()->count(300)->for($account, 'account')->debit()->create([
        'amount' => Money::fromDecimalString('0.07'),
    ]);

    expect($account->calculatedOutstanding()->toDecimalString())->toBe('21.00');
});

test('booting the modules touches no database', function () {
    // A module is a service provider, so nothing stops one from querying at
    // boot — except this. A query per module per request is the cost that
    // grows with the product; this keeps it at zero.
    $queries = 0;

    $app = require base_path('bootstrap/app.php');
    $app->beforeBootstrapping(RegisterProviders::class, function (Application $app) use (&$queries) {
        $app['events']->listen(QueryExecuted::class, function () use (&$queries) {
            $queries++;
        });
    });
    $app->make(ConsoleKernel::class)->bootstrap();

    expect($app->make(ModuleRegistry::class)->all())->toHaveCount(count(config('modules.enabled')))
        ->and($queries)->toBe(0);
});

test('each module adds little to booting the application', function () {
    // The question behind it: does every request pay for a growing list of
    // modules? Measured on the real thing — two applications booted, one with
    // the modules and one without — and compared on their fastest run, which
    // is the least noisy number a shared machine gives.
    //
    // The budget is deliberately generous: registering a module costs tens of
    // microseconds, and the line is at two milliseconds. It exists to fail on
    // a module that starts reading files or scanning directories at boot, not
    // to benchmark.
    $fastest = function (array $modules): float {
        $times = [];

        foreach (range(1, 5) as $run) {
            $start = hrtime(true);
            applicationWithModules($modules);
            $times[] = (hrtime(true) - $start) / 1e6;
        }

        return min($times);
    };

    $modules = config('modules.enabled');

    $perModule = ($fastest($modules) - $fastest([])) / count($modules);

    expect($perModule)->toBeLessThan(2.0);
});

test('the module registry is one instance for the whole application', function () {
    // Modules are declarations, so one set serves every request. Anything
    // derived from them that depends on the signed-in user is scoped instead.
    expect(app(ModuleRegistry::class))->toBe(app(ModuleRegistry::class));
});

test('module routes survive route caching', function () {
    // Modules' routes are loaded from their own files inside the shells'
    // groups. If that did not serialise, production could not cache routes —
    // and every request would register them again. Cached to a scratch path,
    // so the working tree's route cache is never touched.
    $cache = sys_get_temp_dir().'/routes-cache-'.uniqid().'.php';
    putenv("APP_ROUTES_CACHE={$cache}");
    $_ENV['APP_ROUTES_CACHE'] = $_SERVER['APP_ROUTES_CACHE'] = $cache;

    try {
        expect(Artisan::call('route:cache'))->toBe(0)
            ->and(is_file($cache))->toBeTrue();

        $cached = (string) file_get_contents($cache);

        foreach (['admin.catalog.products.index', 'admin.customers.index', 'admin.sales.index', 'admin.dashboard'] as $name) {
            expect($cached)->toContain($name);
        }
    } finally {
        @unlink($cache);
        putenv('APP_ROUTES_CACHE');
        unset($_ENV['APP_ROUTES_CACHE'], $_SERVER['APP_ROUTES_CACHE']);
    }
});
