<?php

use App\Foundation\Area\Area;
use App\Foundation\Money\Money;
use App\Modules\Accounts\Contracts\AccountLedgerInterface;
use App\Modules\Accounts\Contracts\Data\ChargeLine;
use App\Modules\Accounts\Contracts\Data\ChargeRequest;
use App\Modules\Accounts\Contracts\Data\PaymentRequest;
use App\Modules\Customers\Contracts\Data\CustomerData;
use App\Modules\Customers\Domain\Actions\CreateCustomer;
use Illuminate\Support\Str;

/*
 * The quick search and the day's journal in a real browser: Ctrl K from any
 * screen to a customer, «+» to record for them, and both screens through axe
 * with something on them.
 */

/**
 * A customer who took something today and paid part of it.
 */
function servedToday(): CustomerData
{
    $by = actingAsAdmin()->getKey();
    $ledger = app(AccountLedgerInterface::class);
    $customer = app(CreateCustomer::class)->handle(['name' => 'محمد أحمد علي', 'phone' => '01001235678']);

    $ledger->charge(new ChargeRequest($customer->id, $by, (string) Str::uuid(), [ChargeLine::free('طلبات', Money::fromDecimalString('350.00'))]));
    $ledger->payment(new PaymentRequest($customer->id, $by, (string) Str::uuid(), Money::fromDecimalString('100.00')));

    return $customer;
}

function adminPage(string $route, string $language, string $mode, array $parameters = []): mixed
{
    test()->withCookie(Area::Admin->localeCookie(), $language);

    $page = visit(route($route, $parameters, absolute: false));

    return $mode === 'dark' ? $page->inDarkMode() : $page->inLightMode();
}

test('Ctrl K, a name, ↓, + — and the charge drawer is open on their account', function () {
    $customer = servedToday();

    adminPage('admin.catalog.products.index', 'ar', 'light')
        ->keys('.app-content', 'Control+k')
        ->assertVisible('#search-palette.show')
        ->assertScript('document.activeElement.id', 'search-palette-input')
        ->type('#search-palette-input', 'محمد احمد')
        ->assertSee('…5678')
        ->keys('#search-palette-input', 'ArrowDown')
        ->assertScript('document.activeElement.matches("[data-palette-result]")')
        ->keys('#search-palette [data-palette-result] >> nth=0', '+')
        ->assertPathIs("/admin/accounts/{$customer->id}")
        ->assertVisible('#entry-drawer.show')
        ->assertNoJavaScriptErrors();
});

test('the quick search, open on today\'s customers and on what was typed, passes axe', function (string $language, string $mode) {
    servedToday();

    $page = adminPage('admin.dashboard', $language, $mode)
        ->click('.global-search-trigger')
        ->assertVisible('#search-palette.show')
        ->assertSee('محمد أحمد علي');

    // axe reads colours as they are drawn: after the dialog has faded in.
    $settled = '() => new Promise((resolve) => setTimeout(resolve, 400))';
    $page->script($settled);

    $page->assertNoAccessibilityIssues()
        ->type('#search-palette-input', '5678')
        ->assertSee('…5678');

    $page->script($settled);

    $page->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
})->with(['ar', 'en'])->with(['light', 'dark']);

test('the day\'s journal, with entries on it, passes axe and fits a phone', function (string $language, string $mode) {
    servedToday();

    $page = adminPage('admin.accounts.journal', $language, $mode)
        ->assertSee('محمد أحمد علي')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();

    expect(phoneOverflow($page))->toBeLessThanOrEqual(0);
})->with(['ar', 'en'])->with(['light', 'dark']);
