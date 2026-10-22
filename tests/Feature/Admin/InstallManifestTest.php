<?php

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('the panel describes itself to a phone that adds it to its home screen', function () {
    actingAsAdmin();
    readingIn('ar');

    $manifest = $this->get(route('admin.manifest'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->json();

    expect($manifest['name'])->toBe(__('shell.brand.name', [], 'ar'))
        ->and($manifest['lang'])->toBe('ar')
        ->and($manifest['dir'])->toBe('rtl')
        ->and($manifest['start_url'])->toBe('/admin')
        ->and($manifest['scope'])->toBe('/admin/')
        ->and($manifest['display'])->toBe('standalone')
        ->and(collect($manifest['icons'])->pluck('purpose')->all())->toContain('any', 'maskable');

    // Every icon it names is there to be fetched.
    foreach ($manifest['icons'] as $icon) {
        expect(public_path(ltrim((string) parse_url($icon['src'], PHP_URL_PATH), '/')))->toBeFile();
    }
});

test('it speaks the reader\'s language', function () {
    actingAsAdmin();
    readingIn('en');

    expect($this->get(route('admin.manifest'))->json('name'))->toBe(__('shell.brand.name', [], 'en'))
        ->and($this->get(route('admin.manifest'))->json('dir'))->toBe('ltr');
});

test('every admin screen links it, sent with the person\'s cookies, with an icon for a phone', function () {
    actingAsAdmin();

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('<link rel="manifest" href="'.route('admin.manifest').'" crossorigin="use-credentials">', escape: false)
        ->assertSee('rel="apple-touch-icon"', escape: false)
        ->assertSee('<meta name="theme-color"', escape: false);
});

test('only whoever signed in to the panel is told about it', function () {
    $this->get(route('admin.manifest'))->assertRedirect();
});
