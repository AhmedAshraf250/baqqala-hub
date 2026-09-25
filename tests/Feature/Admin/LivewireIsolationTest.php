<?php

use App\Admin\Http\Middleware\EnsureUserIsAdmin;
use App\Admin\Http\Middleware\RequireAdminPasswordConfirmation;
use App\Frontend\Http\Middleware\EnsureUserIsFrontendUser;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Livewire\Component;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleRequests\HandleRequests;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;

uses(LazilyRefreshDatabase::class);

/**
 * A component whose only job is to be acted on.
 */
class ConfirmedScreenProbe extends Component
{
    public int $presses = 0;

    public function press(): void
    {
        $this->presses++;
    }

    public function render(): string
    {
        return '<div>{{ $presses }}</div>';
    }
}

/**
 * A screen behind the admin password confirmation, the way `/admin/access`
 * will be once it is built, with the probe on it.
 */
function mountConfirmedScreen(): string
{
    Livewire::component('confirmed-screen-probe', ConfirmedScreenProbe::class);

    Route::middleware(['web', 'auth:admin', 'admin', 'admin.password.confirm'])
        ->get('admin/_confirmed-screen', fn () => Blade::render('<livewire:confirmed-screen-probe />'));

    $html = test()->get('/admin/_confirmed-screen')->assertOk()->getContent();

    preg_match('/wire:snapshot="([^"]+)"/', $html, $snapshot);

    return html_entity_decode($snapshot[1]);
}

/**
 * Press the probe's button the way the browser does: a POST to Livewire's
 * single update endpoint, sent from the admin page.
 */
function pressProbe(string $snapshot, bool $asJsonClient = true): TestResponse
{
    $payload = [
        'components' => [[
            'snapshot' => $snapshot,
            'updates' => [],
            'calls' => [['path' => '', 'method' => 'press', 'params' => []]],
        ]],
    ];

    $request = test()->withHeaders(['X-Livewire' => 'true', 'Referer' => url('/admin/_confirmed-screen')]);

    // Livewire's own `fetch` sends a JSON body with the browser's default
    // `Accept: */*`; a JSON client asks for JSON back.
    return $request->json('POST', app(HandleRequests::class)->getUpdateUri(), $payload, [
        'Accept' => $asJsonClient ? 'application/json' : '*/*',
    ]);
}

test('livewire re-runs each area\'s own middleware on every update', function () {
    // Livewire sends every action to one endpoint and re-runs only the
    // middleware it is told to. Laravel's guard check is on its list by
    // default; the area checks and the admin password confirmation are not.
    $persistent = app(PersistentMiddleware::class)->getPersistentMiddleware();

    expect($persistent)->toContain(EnsureUserIsAdmin::class)
        ->toContain(RequireAdminPasswordConfirmation::class)
        ->toContain(EnsureUserIsFrontendUser::class);
});

test('a confirmed admin screen keeps working while the confirmation holds', function () {
    actingAsAdmin();
    $this->withSession([RequireAdminPasswordConfirmation::SessionKey => now()->unix()]);

    pressProbe(mountConfirmedScreen())->assertOk();
});

test('a confirmed admin screen stops accepting actions once the confirmation expires', function () {
    // The defect this closes: the page was confirmed when it loaded, and every
    // button on it kept working for as long as the tab stayed open.
    actingAsAdmin();
    $this->withSession([RequireAdminPasswordConfirmation::SessionKey => now()->unix()]);

    $snapshot = mountConfirmedScreen();

    $this->travel((int) config('auth.password_timeout') + 1)->seconds();

    pressProbe($snapshot)->assertStatus(423);
});

test('a browser pressing a button after the confirmation expires is sent to confirm again', function () {
    actingAsAdmin();
    $this->withSession([RequireAdminPasswordConfirmation::SessionKey => now()->unix()]);

    $snapshot = mountConfirmedScreen();

    $this->travel((int) config('auth.password_timeout') + 1)->seconds();

    pressProbe($snapshot, asJsonClient: false)->assertRedirect(route('admin.password.confirm'));
});
