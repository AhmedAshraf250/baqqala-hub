<?php

use App\Modules\Catalog\CatalogServiceProvider;
use App\Modules\Customers\Database\Models\Customer;
use App\Modules\Purchases\PurchasesServiceProvider;
use App\Modules\Sales\SalesServiceProvider;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(LazilyRefreshDatabase::class);

/**
 * Run a command and return what it printed.
 *
 * @param  array<string, mixed>  $parameters
 */
function consoleOutput(string $command, array $parameters = []): string
{
    Artisan::call($command, $parameters);

    return Artisan::output();
}

test('the list shows every module, disabled ones too, and where each stands', function () {
    $this->seed(AuthorizationSeeder::class);

    // Catalog is disabled with its tables left; Purchases is disabled and
    // then uninstalled, so nothing of it is stored.
    config(['modules.enabled' => array_values(array_diff(config('modules.enabled'), [
        CatalogServiceProvider::class, PurchasesServiceProvider::class, SalesServiceProvider::class,
    ]))]);
    Artisan::call('app:module:uninstall', ['module' => 'purchases', '--force' => true]);

    $states = collect(explode("\n", consoleOutput('app:module:list')))
        ->mapWithKeys(fn (string $line) => preg_match('/^\|\s*([a-z]+)\s*\|[^|]*\|\s*([^|]+?)\s*\|/', $line, $cell) === 1 ? [$cell[1] => $cell[2]] : []);

    expect($states['customers'])->toBe('enabled')
        ->and($states['catalog'])->toBe('disabled — its data stays')
        ->and($states['purchases'])->toBe('not installed');
});

test('show says what a module declares, what needs it, and what of it is stored', function () {
    $this->seed(AuthorizationSeeder::class);
    Customer::factory()->count(2)->create();

    expect(consoleOutput('app:module:show', ['module' => 'customers']))
        ->toContain('App\Modules\Customers\CustomersServiceProvider')
        ->toMatch('/Needed by\W+accounts, sales/')
        ->toMatch('/Implements\W+.*UninstallableInterface/')
        ->toMatch('/Routes · admin\W+Areas\/Admin\/routes\.php · \d+ routes?/')
        ->toMatch('/Publishes\W+CustomerRepositoryInterface/')
        ->toContain('app:customers:grant-portal-access')
        ->toMatch('/table customers\W+2 rows/')
        ->toContain('customers.view');
});

test('show describes a disabled module from what it declares', function () {
    config(['modules.enabled' => array_values(array_diff(config('modules.enabled'), [
        CatalogServiceProvider::class, PurchasesServiceProvider::class, SalesServiceProvider::class,
    ]))]);

    expect(consoleOutput('app:module:show', ['module' => 'catalog']))
        ->toContain('disabled — its data stays')
        ->toMatch('/Routes · admin\W+Areas\/Admin\/routes\.php\s*$/m')
        ->toContain('not registered while disabled')
        ->toMatch('/table products\W+0 rows/');
});

test('only an enabled module\'s migrations are counted as waiting', function () {
    // `migrate` never runs a disabled module's migrations, so telling someone
    // to run it for one is telling them to do nothing.
    $this->seed(AuthorizationSeeder::class);
    config(['modules.enabled' => array_values(array_diff(config('modules.enabled'), [
        CatalogServiceProvider::class, PurchasesServiceProvider::class, SalesServiceProvider::class,
    ]))]);
    Artisan::call('app:module:uninstall', ['module' => 'purchases', '--force' => true]);
    Artisan::call('app:module:uninstall', ['module' => 'sales', '--force' => true]);
    Artisan::call('app:module:uninstall', ['module' => 'catalog', '--force' => true]);

    expect(consoleOutput('about', ['--only' => 'foundation']))->toMatch('/Module migrations pending\W+none/')
        ->and(consoleOutput('app:module:show', ['module' => 'catalog']))->toContain('0 ran, 2 not run while it is disabled');
});

test('an unknown module is named in the refusal', function () {
    $this->artisan('app:module:show', ['module' => 'nope'])
        ->expectsOutputToContain('There is no module [nope]')
        ->assertFailed();
});

test('about shows the areas and the modules', function () {
    expect(consoleOutput('about', ['--only' => 'foundation']))
        ->toMatch('/Area admin\W+guard admin · cookie \w+_admin/')
        ->toMatch('/Area frontend\W+guard web/')
        ->toMatch('/Modules enabled\W+.*\bcustomers\b/')
        ->toMatch('/Module migrations pending\W+none/');
});

test('about says whether web requests run with OPcache', function () {
    // php.ini belongs to the machine, so a new one starts without it — and
    // every page then takes about three times as long.
    $expected = extension_loaded('Zend OPcache') && filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOLEAN)
        ? '/OPcache\W+on for web requests/'
        : '/OPcache\W+OFF/';

    expect(consoleOutput('about', ['--only' => 'foundation']))->toMatch($expected);
});

test('about says when the permissions the modules define are not stored', function () {
    // A module enabled without reseeding: its screens would refuse everyone
    // but the unrestricted role, with nothing saying why.
    expect(consoleOutput('about', ['--only' => 'admin_area']))->toMatch('/Permissions\W+\d+ defined, \d+ not stored — see php artisan app:sync/');

    $this->seed(AuthorizationSeeder::class);

    expect(consoleOutput('about', ['--only' => 'admin_area']))->toMatch('/Permissions\W+\d+ defined, all stored/');
});
