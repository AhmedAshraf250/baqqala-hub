<?php

use App\Foundation\Settings\SettingStore;
use App\Foundation\Time\LocalClock;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

test('whoever may configure the panel finds the business tab', function () {
    actingAsAdmin();

    $this->get(route('admin.settings', ['tab' => 'business']))
        ->assertOk()
        ->assertSee('id="settings-business"', escape: false)
        ->assertSee(__('shell.settings.timezone'));
});

test('an administrator who may not configure the panel never sees it', function () {
    $cashier = actingAsAdmin();
    $cashier->syncRoles('cashier');

    $this->get(route('admin.settings'))
        ->assertOk()
        ->assertDontSee('id="settings-business"', escape: false);
});

test('the tab opens on the zone and day start in use', function () {
    actingAsAdmin();

    Livewire::test('admin::page.settings.business')
        ->assertSet('timezone', 'Africa/Cairo')
        ->assertSet('dayStartsAt', '00:00');
});

test('saving changes how times read, and records who changed it', function () {
    $owner = actingAsAdmin();

    Livewire::test('admin::page.settings.business')
        ->set('timezone', 'Asia/Riyadh')
        ->set('dayStartsAt', '04:00')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.settings', ['tab' => 'business']));

    app()->forgetScopedInstances();

    $clock = app(LocalClock::class);

    expect($clock->timezone())->toBe('Asia/Riyadh')
        ->and($clock->dayStartsAt())->toBe('04:00')
        ->and(DB::table('settings')->where('key', LocalClock::TIMEZONE)->value('updated_by'))->toBe($owner->getKey());
});

test('a zone or hour the form does not offer is refused', function (string $field, string $value) {
    actingAsAdmin();

    Livewire::test('admin::page.settings.business')
        ->set($field, $value)
        ->call('save')
        ->assertHasErrors($field);

    expect(app(SettingStore::class)->get(LocalClock::TIMEZONE))->toBeNull();
})->with([
    'an unknown zone' => ['timezone', 'Mars/Olympus'],
    'an hour past the small hours' => ['dayStartsAt', '13:00'],
    'not a time at all' => ['dayStartsAt', 'soon'],
]);

test('an administrator without the permission cannot save through the component', function () {
    // Hiding the tab is not authorizing the pane: a Livewire action reaches
    // the component directly.
    $cashier = actingAsAdmin();
    $cashier->syncRoles('cashier');

    Livewire::test('admin::page.settings.business')
        ->set('timezone', 'Asia/Riyadh')
        ->call('save')
        ->assertForbidden();

    expect(app(SettingStore::class)->get(LocalClock::TIMEZONE))->toBeNull();
});

test('the preview shows the time in the zone being chosen', function () {
    actingAsAdmin();
    $this->travelTo(now()->setDateTime(2026, 10, 2, 19, 54, 0));

    // 19:54 UTC is 10:54 at night in Cairo.
    Livewire::test('admin::page.settings.business')
        ->assertSee('10:54');
});
