<?php

use Illuminate\Support\Facades\Blade;
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
