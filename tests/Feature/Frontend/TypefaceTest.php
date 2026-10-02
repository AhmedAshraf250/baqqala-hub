<?php

use App\Foundation\Area\Area;

/*
 * The frontend's face, Instrument Sans, has no Arabic. Arabic reads in Cairo:
 * the whole of an Arabic page, and any Arabic on an English one.
 */

/**
 * The frontend stylesheet as the browser receives it.
 */
function builtFrontendStylesheet(): string
{
    return (string) file_get_contents(public_path('build/'.builtAsset('resources/css/frontend/app.css')));
}

test('an arabic page leads with cairo, and serves it arabic included', function () {
    $this->withVite();

    $html = $this->withCookie(Area::Frontend->localeCookie(), 'ar')
        ->get(route('frontend.home'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('lang="ar"')
        ->toMatch('/@font-face\s*\{[^}]*font-family:\s*"Cairo";[^}]*unicode-range:\s*U\+0600-06FF/')
        ->and(builtFrontendStylesheet())->toMatch('/:root:lang\(ar\)\s*\{[^}]*--font-sans:\s*"Cairo"/');
});

test('an english page draws its arabic in cairo, not in whatever the system has', function () {
    expect(builtFrontendStylesheet())->toMatch('/--font-sans:\s*"Instrument Sans",\s*"Cairo",/');
});
