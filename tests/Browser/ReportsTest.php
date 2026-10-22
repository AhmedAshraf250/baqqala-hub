<?php

use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\AdminUser;
use App\Foundation\Money\Money;
use App\Modules\Accounts\Contracts\AccountLedgerInterface;
use App\Modules\Accounts\Contracts\Data\ChargeLine;
use App\Modules\Accounts\Contracts\Data\ChargeRequest;
use App\Modules\Accounts\Contracts\Data\PaymentRequest;
use App\Modules\Accounts\Contracts\Data\VoidRequest;
use App\Modules\Accounts\Contracts\Enums\CorrectionReason;
use App\Modules\Catalog\Database\Models\Category;
use App\Modules\Catalog\Database\Models\Product;
use App\Modules\Catalog\Domain\Actions\ChangeProductPrice;
use App\Modules\Customers\Contracts\CustomerDirectoryInterface;
use App\Modules\Customers\Contracts\Data\CustomerData;
use App\Modules\Customers\Domain\Actions\CreateCustomer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/*
 * The reports and the change log in a real browser, with something on them:
 * every screen through axe in both languages and both modes, and on a phone
 * without a sideways scroll.
 */

/**
 * Two customers' months: one who took rice and cheese and paid part, one who
 * has owed since the summer; a charge undone at once; a mobile and a price
 * changed.
 */
function keptBooks(): void
{
    $by = actingAsAdmin()->getKey();
    $ledger = app(AccountLedgerInterface::class);
    $create = app(CreateCustomer::class);

    test()->travelTo(CarbonImmutable::parse('2026-06-20 10:00', 'Africa/Cairo'));
    $late = $create->handle(['name' => 'كريم حسن', 'phone' => '01112223334']);
    $ledger->charge(new ChargeRequest($late->id, $by, (string) Str::uuid(), [ChargeLine::free('طلبات', Money::fromDecimalString('400.00'))]));

    test()->travelTo(CarbonImmutable::parse('2026-10-01 11:00', 'Africa/Cairo'));
    $shelf = Category::factory()->create(['name' => 'أرز ومكرونة']);
    $rice = Product::factory()->for($shelf)->priced('150.00')->create(['name' => 'أرز الضحى 5 كجم']);
    $cheese = Product::factory()->for($shelf)->byWeight()->priced('140.00')->create(['name' => 'جبنة بيضاء سائبة']);
    $customer = $create->handle(['name' => 'محمد أحمد علي', 'phone' => '01001235678']);

    $ledger->charge(new ChargeRequest($customer->id, $by, (string) Str::uuid(), [ChargeLine::product($rice->id, 1000), ChargeLine::product($cheese->id, 1250)]));
    $wrong = $ledger->charge(new ChargeRequest($customer->id, $by, (string) Str::uuid(), [ChargeLine::free('طلبات', Money::fromDecimalString('90.00'))]))->first()->entryId;
    $ledger->void(new VoidRequest($wrong, $by, (string) Str::uuid(), CorrectionReason::Undo));
    $ledger->payment(new PaymentRequest($customer->id, $by, (string) Str::uuid(), Money::fromDecimalString('100.00')));

    app(CustomerDirectoryInterface::class)->update(new CustomerData($customer->id, 'محمد أحمد علي', '01009998888', null, null));
    app(ChangeProductPrice::class)->handle($rice->id, Money::fromDecimalString('160.00'));

    test()->travelTo(CarbonImmutable::parse('2026-10-02 18:00', 'Africa/Cairo'));
}

function reportPage(string $route, string $language, string $mode): mixed
{
    test()->withCookie(Area::Admin->localeCookie(), $language);

    $page = visit(route($route, absolute: false));

    return $mode === 'dark' ? $page->inDarkMode() : $page->inLightMode();
}

test('every report, and the change log, with entries on it, passes axe and fits a phone', function (string $route, string $seen, string $language, string $mode) {
    keptBooks();

    $page = reportPage($route, $language, $mode)
        ->assertSee($seen)
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();

    expect(phoneOverflow($page))->toBeLessThanOrEqual(0);
})->with([
    'index' => ['admin.reports.index', 'جبنة بيضاء سائبة'],
    'debts' => ['admin.reports.debts', 'كريم حسن'],
    'aging' => ['admin.reports.aging', 'كريم حسن'],
    'activity' => ['admin.reports.activity', '325.00'],
    'staff' => ['admin.reports.staff', '100.00'],
    'corrections' => ['admin.reports.corrections', '90.00'],
    'products' => ['admin.reports.products', 'جبنة بيضاء سائبة'],
    'change log' => ['admin.audit', '01009998888'],
])->with(['ar', 'en'])->with(['light', 'dark']);

test('a long name at the head of a report card is cut short, and never widens the page', function (string $language) {
    keptBooks();

    // The staff card leads with whoever collected most; a name with no room
    // to wrap once stretched the whole page past a phone's edge.
    AdminUser::query()->update(['name' => 'عبد الرحمن محمد عبد العزيز الشناوي الكبير']);

    $page = reportPage('admin.reports.index', $language, 'light')
        ->assertSee('عبد الرحمن محمد');

    expect(phoneOverflow($page))->toBeLessThanOrEqual(0);
})->with(['ar', 'en']);

test('a bucket narrows the call list, and a period is a link', function () {
    keptBooks();

    reportPage('admin.reports.aging', 'ar', 'light')
        ->click('.stat-box-action >> nth=3')
        ->assertQueryStringHas('bucket', 'over_90')
        ->assertSee('كريم حسن')
        ->assertDontSee('01009998888')
        ->assertNoJavaScriptErrors();

    reportPage('admin.reports.activity', 'en', 'light')
        ->select('#report-period', 'custom')
        ->assertVisible('#report-from')
        ->assertQueryStringHas('period', 'custom')
        ->assertNoJavaScriptErrors();
});
