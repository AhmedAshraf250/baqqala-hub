<?php

use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\AdminUser;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * The header's quick search, as the shell provides it: on every screen,
 * closed and silent until opened, and finding the panel's own screens.
 */

/**
 * @return list<array{0: string, 1: list<string>}>
 */
function paletteGroups(mixed $palette): array
{
    return array_map(
        fn (array $group): array => [$group['label'], array_map(fn ($result) => $result->title, $group['results'])],
        $palette->instance()->groups,
    );
}

test('every admin screen carries the quick search, which asks nothing until it is opened', function () {
    actingAsAdmin();

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('id="search-palette"', escape: false)
        ->assertSee('data-search-palette-open', escape: false);

    DB::enableQueryLog();
    $palette = Livewire::test('admin::layout.search-palette');

    expect($palette->instance()->groups)->toBe([])
        ->and(DB::getQueryLog())->toBe([]);

    $palette->call('open')
        ->assertSet('opened', true)
        ->assertDispatched('overlay-show', id: 'search-palette');
});

test('a screen is found by its name in the sidebar', function () {
    actingAsAdmin();

    $palette = Livewire::test('admin::layout.search-palette')->call('open')->set('q', __('shell.navigation.settings'));

    expect(paletteGroups($palette))->toContain([__('shell.search.screens'), [__('shell.navigation.settings')]])
        ->and(collect($palette->instance()->groups)->last()['results'][0]->url)->toBe(route('admin.settings'));
});

test('a screen this administrator may not open is not found', function () {
    actingAsAdmin();
    $cashier = AdminUser::factory()->create();
    $cashier->syncRoles('cashier');
    $this->actingAs($cashier, Area::Admin->guard());

    $palette = Livewire::test('admin::layout.search-palette')->call('open')->set('q', __('shell.navigation.roles'));

    expect(collect(paletteGroups($palette))->pluck(1)->flatten()->all())->not->toContain(__('shell.navigation.roles'));
});

test('a word that finds nothing says so', function () {
    actingAsAdmin();
    // Signed in with no role: nothing to search but the screens anyone may open.
    $this->actingAs(AdminUser::factory()->create(), Area::Admin->guard());

    Livewire::test('admin::layout.search-palette')
        ->call('open')
        ->set('q', 'zzzz')
        ->assertSee(__('shell.search.nothing', ['term' => 'zzzz']));
});
