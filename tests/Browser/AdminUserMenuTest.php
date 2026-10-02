<?php

use App\Foundation\Area\Area;

/*
 * The admin's user menu, open. The screen-by-screen checks see every page with
 * its menus closed, and axe does not look inside a closed menu — so the menu's
 * contrast in the dark mode is checked here, with the menu showing.
 */

test('the open user menu passes axe', function (string $language, string $mode) {
    actingAsAdmin();
    $this->withCookie(Area::Admin->localeCookie(), $language);

    $page = visit(route('admin.dashboard', absolute: false));
    $page = $mode === 'dark' ? $page->inDarkMode() : $page->inLightMode();

    $page->click('.user-menu > .dropdown-toggle')
        ->assertVisible('.user-menu .user-footer')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
})->with(['ar', 'en'])->with(['light', 'dark']);
