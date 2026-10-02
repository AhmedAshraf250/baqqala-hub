<?php

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\Fixtures\Modules\Probe\ProbeServiceProvider;

/*
 * A module's views are reached the way an area's are: as a view, as a Blade
 * component, and as a Livewire screen. Before this, only the first worked, so
 * the first Livewire screen inside a module would not have resolved.
 */

beforeEach(function () {
    app()->register(ProbeServiceProvider::class);
});

test('registered view namespaces can be cached even before modules have templates', function () {
    // Into a scratch directory: `view:cache` begins with `view:clear`, and run
    // against storage/ it deleted the compiled views — Livewire's included —
    // that every later test in the run was reading.
    // A fresh application, so every compiler is built with that path from the
    // start, and every enabled module is registered the way it is in use.
    $compiled = sys_get_temp_dir().'/compiled-views-'.uniqid();
    File::ensureDirectoryExists($compiled);

    $app = require base_path('bootstrap/app.php');
    $app->afterBootstrapping(
        LoadConfiguration::class,
        static fn (Application $app) => $app['config']->set('view.compiled', $compiled),
    );
    $kernel = $app->make(ConsoleKernel::class);
    $kernel->bootstrap();

    try {
        expect($kernel->call('view:cache'))->toBe(0)
            ->and(File::allFiles($compiled))->not->toBeEmpty();
    } finally {
        File::deleteDirectory($compiled);
    }
});

test('every enabled module has the views directory its namespace points at', function () {
    // The namespace is registered without looking at the disk, and
    // `view:cache` — run by `optimize` — fails on a directory that is not there.
    foreach (config('modules.enabled') as $provider) {
        $views = dirname((new ReflectionClass($provider))->getFileName()).'/Resources/views';

        expect(is_dir($views))->toBeTrue("{$provider} has no Resources/views directory");
    }
});

test('a module\'s view renders under its key', function () {
    expect(view('probe::frontend.greeting')->render())->toContain('Hello from a module');
});

test('a module\'s anonymous component renders under its key', function () {
    expect(Blade::render('<x-probe::frontend.badge>New</x-probe::frontend.badge>'))
        ->toContain('<span class="probe-badge">New</span>');
});

test('a module\'s component with data is rendered through its class', function () {
    // The template alone could not know the total: the class worked it out.
    expect(Blade::render('<x-probe::admin.tally :of="4" />'))
        ->toContain('<span class="probe-tally">8 counted</span>');
});

test('a module\'s Livewire screen resolves and runs under its key', function () {
    Livewire::test('probe::admin.counter')
        ->assertSee('Pressed 0 times')
        ->call('press')
        ->assertSee('Pressed 1 times');
});

test('a module\'s screen is built from its area\'s components, not its own copies', function () {
    // resources/views holds each area's shell and component library; a module
    // composes them and adds only what is its own.
    $html = view('probe::admin.panel')->render();

    expect($html)->toContain('class="card-title')
        ->toContain('From a module')
        ->toContain('Built on the shell');
});
