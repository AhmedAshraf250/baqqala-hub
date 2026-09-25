<?php

use App\Admin\Http\Middleware\RequireAdminPasswordConfirmation;
use App\Foundation\Identity\Models\AdminUser;
use App\Foundation\Localization\LocalePreference;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('an admin screen renders its shell exactly once', function (string $route) {
    actingAsAdmin();
    $this->withSession([RequireAdminPasswordConfirmation::SessionKey => time()]);

    $html = $this->get(route($route))->assertOk()->getContent();

    // A layout applied twice — once by #[Layout] and once by the view wrapping
    // itself — renders the whole chrome nested inside itself.
    expect(substr_count($html, '<html'))->toBe(1, "[{$route}] rendered more than one document.")
        ->and(substr_count($html, 'class="app-wrapper"'))->toBe(1, "[{$route}] rendered more than one shell.")
        ->and(substr_count($html, 'app-sidebar'))->toBe(1, "[{$route}] rendered more than one sidebar.")
        ->and(substr_count($html, 'class="app-header'))->toBe(1, "[{$route}] rendered more than one header.");
})->with(adminScreens());

test('an admin screen never renders the customer stack', function (string $route) {
    actingAsAdmin();
    $this->withSession([RequireAdminPasswordConfirmation::SessionKey => time()]);

    $this->get(route($route))
        ->assertOk()
        ->assertDontSee('flux', false)
        ->assertDontSee('wire:navigate', false);
})->with(adminScreens());

test('an admin screen loads its own direction stylesheet and no other', function (string $route) {
    // The suite disables Vite by default, which would make every assertion
    // below pass against an empty string. Asset wiring has to be tested for
    // real or not at all.
    $this->withVite();
    actingAsAdmin();
    $this->withSession([RequireAdminPasswordConfirmation::SessionKey => time()]);

    $html = $this->get(route($route))->assertOk()->getContent();

    // AdminLTE's docs are explicit that the LTR and RTL builds must never load
    // together, and the customer bundle must never appear here at all.
    expect($html)->toContain(builtAsset('resources/css/admin/app.css'))
        ->not->toContain(builtAsset('resources/css/admin/app.rtl.css'))
        ->not->toContain(builtAsset('resources/css/frontend/app.css'))
        ->not->toContain(builtAsset('resources/js/frontend/app.js'));
})->with(adminScreens());

test('an arabic admin screen swaps to the mirrored stylesheet', function () {
    $this->withVite();
    actingAsAdmin();

    $this->withCookie(LocalePreference::Cookie, 'ar');

    $html = $this->get(route('admin.dashboard'))->assertOk()->getContent();

    expect($html)->toContain(builtAsset('resources/css/admin/app.rtl.css'))
        ->not->toContain(builtAsset('resources/css/admin/app.css'))
        ->toContain('dir="rtl"');
});

test('an admin screen serves cairo itself, arabic included, preloading only its body weight', function (string $locale) {
    // The stylesheet always led with Cairo, but no admin layout served its
    // faces, so it showed only on a machine that had it installed.
    $this->withVite();
    actingAsAdmin();

    $html = $this->withCookie(LocalePreference::Cookie, $locale)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->getContent();

    expect($html)->toMatch('/@font-face\s*\{[^}]*font-family:\s*"Cairo";[^}]*unicode-range:\s*U\+0600-06FF/');

    preg_match_all('/<link [^>]*rel="preload"[^>]*cairo-(\d+)-[^>]*>/', $html, $preloads);

    expect($preloads[1])->not->toBeEmpty()
        ->and(array_values(array_unique($preloads[1])))->toBe(['400']);
})->with(['ar', 'en']);

test('the fullscreen toggle ships both icons the plugin swaps', function () {
    actingAsAdmin();

    $html = $this->get(route('admin.dashboard'))->assertOk()->getContent();

    // AdminLTE's FullScreen plugin toggles `d-none` between these two nodes;
    // with only one present the icon never changes state.
    expect($html)->toContain('data-lte-icon="maximize"')
        ->toContain('data-lte-icon="minimize"');

    preg_match('/data-lte-icon="minimize"[^>]*class="([^"]*)"/', $html, $minimize);

    // The minimize icon must start hidden, or both show at once on load.
    expect($minimize[1] ?? '')->toContain('d-none');
});

test('the admin auth screens render a single standalone document', function (string $route) {
    $html = $this->get(route($route))->assertOk()->getContent();

    expect(substr_count($html, '<html'))->toBe(1)
        ->and($html)->toContain('login-box')
        // A signed-out visitor has no navigation to show.
        ->and($html)->not->toContain('app-sidebar');
})->with(['admin.login']);

test('a screen without a title of its own is named by the sidebar, in the tab too', function () {
    // The content header already fell back to the navigation tree; the
    // browser tab did not, and every unbuilt section read only the product's name.
    actingAsAdmin();

    $html = $this->get(route('admin.customers.index'))->assertOk()->getContent();

    expect($html)->toContain('<title>'.e(__('customers::module.navigation.customers')).' — '.e(__('shell.brand.name')).'</title>');
});

test('what the last action flashed is shown once, as an alert', function () {
    actingAsAdmin();

    $html = $this->withSession(['success' => 'The price list was saved.'])
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->getContent();

    expect(substr_count($html, 'The price list was saved.'))->toBe(1)
        ->and($html)->toContain('alert-success');
});

test('the user menu is laid out the way AdminLTE draws it', function () {
    // A coloured header naming the person, a row of their own settings, and a
    // footer with their profile and signing out — the template's user menu,
    // not a list of links.
    actingAsAdmin(AdminUser::factory()->create(['name' => 'Umm Ahmed']));

    // AdminLTE's own selectors, so its styles are known to reach the markup.
    $document = HTMLDocument::createFromString($this->get(route('admin.dashboard'))->assertOk()->getContent(), LIBXML_NOERROR);
    $menu = '.navbar-nav > .user-menu > .dropdown-menu';

    $header = (string) $document->querySelector("{$menu} > li.user-header")?->textContent;

    expect($header)->toContain('Umm Ahmed')
        ->toContain(__('shell.roles.owner'))
        ->toContain(__('shell.profile.member_since', ['date' => now()->isoFormat('MMMM YYYY')]));

    $settings = array_map(
        fn ($link) => $link->getAttribute('href'),
        iterator_to_array($document->querySelectorAll("{$menu} > li.user-body a")),
    );

    expect($settings)->toBe([
        route('admin.settings', ['tab' => 'account']),
        route('admin.settings', ['tab' => 'security']),
        route('admin.settings', ['tab' => 'appearance']),
    ]);

    expect($document->querySelector("{$menu} > li.user-footer > a.btn[href=\"".route('admin.profile').'"]'))->not->toBeNull()
        ->and($document->querySelector("{$menu} > li.user-footer > form[method=\"POST\"][action=\"".route('admin.logout').'"] > button[type="submit"]'))->not->toBeNull();
});

test('each settings link in the user menu opens its own tab', function (string $tab) {
    actingAsAdmin();

    $active = HTMLDocument::createFromString($this->get(route('admin.settings', ['tab' => $tab]))->assertOk()->getContent(), LIBXML_NOERROR)
        ->querySelector('.tab-pane.active');

    expect($active?->getAttribute('id'))->toBe("settings-{$tab}");
})->with(['account', 'security', 'appearance']);
