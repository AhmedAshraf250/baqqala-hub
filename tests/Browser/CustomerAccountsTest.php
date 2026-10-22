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
use App\Modules\Accounts\Contracts\Enums\OverpaymentChoice;
use App\Modules\Accounts\Contracts\Enums\TransactionType;
use App\Modules\Accounts\Database\Models\CustomerAccount;
use App\Modules\Accounts\Domain\Actions\CorrectEntry;
use App\Modules\Accounts\Domain\Actions\RecordOpeningBalance;
use App\Modules\Catalog\Database\Models\Category;
use App\Modules\Catalog\Database\Models\Product;
use App\Modules\Customers\Contracts\Data\CustomerData;
use App\Modules\Customers\Contracts\Enums\GroupColor;
use App\Modules\Customers\Database\Models\CustomerGroup;
use App\Modules\Customers\Domain\Actions\CreateCustomer;
use Illuminate\Support\Str;

/*
 * The counter's workspace with something on it: the screen-by-screen checks
 * see the list empty and the drawer closed, and axe does not look inside a
 * closed drawer. Here the list has customers in every state, the drawer is
 * open with the picker's results showing, and the main journeys are worked
 * from the keyboard, as the counter works them.
 */

/**
 * A shop with a few shelves, and customers who owe, are owed, and are
 * settled — in groups of every colour.
 *
 * @return array{owing: CustomerData}
 */
function counterWithCustomers(): array
{
    $by = actingAsAdmin()->getKey();
    $ledger = app(AccountLedgerInterface::class);

    $rice = Category::factory()->create(['name' => 'أرز ومكرونة', 'icon' => 'basket']);
    $oils = Category::factory()->create(['name' => 'زيوت وسمنة', 'icon' => 'droplet']);
    Product::factory()->for($rice)->priced('150.00')->create(['name' => 'أرز الضحى 5 كجم']);
    Product::factory()->for($oils)->priced('90.00')->create(['name' => 'زيت كريستال 1 لتر', 'search_terms' => 'crystal']);
    Product::factory()->for($rice)->priced('15.00')->create(['name' => 'بسكويت أوريو']);

    $groups = collect(GroupColor::cases())->map(fn (GroupColor $color, int $i) => CustomerGroup::factory()->create(['name' => "مجموعة {$i}", 'color' => $color]));

    $owing = app(CreateCustomer::class)->handle(['name' => 'محمد أحمد علي', 'phone' => '01001235678']);
    $ledger->charge(new ChargeRequest($owing->id, $by, (string) Str::uuid(), [ChargeLine::free('حاجات', Money::fromDecimalString('900.00'))]));

    $inCredit = app(CreateCustomer::class)->handle(['name' => 'سعيد للأدوات']);
    $ledger->payment(new PaymentRequest($inCredit->id, $by, (string) Str::uuid(), Money::fromDecimalString('50.00'), overpayment: OverpaymentChoice::KeepAsCredit));

    $settled = app(CreateCustomer::class)->handle(['name' => 'Mostafa Abdallah', 'phone' => '01091112233']);

    $overLimit = app(CreateCustomer::class)->handle(['name' => 'نادية سمير']);
    $ledger->charge(new ChargeRequest($overLimit->id, $by, (string) Str::uuid(), [ChargeLine::free('حاجات', Money::fromDecimalString('1040.00'))]));
    CustomerAccount::query()->where('customer_id', $overLimit->id)->update(['credit_limit' => 100000]);

    foreach ([$owing, $inCredit, $settled, $overLimit] as $index => $customer) {
        $groups[$index]->members()->attach($customer->id);
        $groups[$index + 4]->members()->attach($customer->id);
    }

    return ['owing' => $owing];
}

function counterPage(string $language, string $mode): mixed
{
    test()->withCookie(Area::Admin->localeCookie(), $language);

    $page = visit(route('admin.accounts.index', absolute: false));

    return $mode === 'dark' ? $page->inDarkMode() : $page->inLightMode();
}

test('the browser folds search text exactly as the server does', function () {
    actingAsAdmin();

    $fixture = json_decode((string) file_get_contents(base_path('tests/Fixtures/search-text.json')), true, flags: JSON_THROW_ON_ERROR);
    $inputs = array_column($fixture['cases'], 'input');

    $folded = visit(route('admin.accounts.index', absolute: false))->script('() => '.json_encode($inputs, JSON_UNESCAPED_UNICODE).'.map((input) => {
        const asked = new CustomEvent("search-text-normalize", { detail: { input } })
        document.dispatchEvent(asked)

        return asked.detail.output
    })');

    expect($folded)->toBe(array_column($fixture['cases'], 'expected'));
});

test('the list, with customers in every state on it, passes axe', function (string $language, string $mode) {
    counterWithCustomers();

    counterPage($language, $mode)
        ->assertSee('محمد أحمد علي')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
})->with(['ar', 'en'])->with(['light', 'dark']);

test('the drawer, open with the picker\'s results showing, passes axe', function (string $language, string $mode) {
    counterWithCustomers();

    counterPage($language, $mode)
        ->click('#customer-rows [data-row]:first-child [data-row-key="+"]')
        ->assertVisible('#entry-drawer.show')
        ->type('#entry-search', 'زيت')
        ->assertSee('زيت كريستال 1 لتر')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
})->with(['ar', 'en'])->with(['light', 'dark']);

test('the main journey from the keyboard: /, a name, ↓, +, 350, Ctrl+Enter', function () {
    ['owing' => $customer] = counterWithCustomers();

    $page = counterPage('ar', 'light')
        ->keys('.app-content', '/')
        ->assertScript('document.activeElement.id', 'customer-search')
        ->type('#customer-search', 'محمد احمد')
        ->assertDontSee('سعيد للأدوات')
        ->keys('#customer-search', 'ArrowDown')
        ->keys('#customer-rows [data-row]', '+')
        ->assertVisible('#entry-drawer.show')
        ->assertScript('document.activeElement.id', 'entry-search')
        ->type('#entry-search', '350')
        ->keys('#entry-search', 'Control+Enter')
        ->assertSee(__('accounts::module.entry.posted_charge', ['name' => 'محمد أحمد علي', 'amount' => '350.00', 'balance' => __('accounts::module.balance.owes').' 1,250.00']))
        ->assertNoJavaScriptErrors();

    expect(CustomerAccount::query()->where('customer_id', $customer->id)->firstOrFail()->outstanding->toDecimalString())->toBe('1250.00');
});

test('your example in the picker: «2*رز 5», «زيت كر», «3* اوريو», Ctrl+Enter', function () {
    ['owing' => $customer] = counterWithCustomers();

    counterPage('ar', 'light')
        // The first row is whoever posted last; this is Mohamed's own button.
        ->click('[aria-label="'.__('accounts::module.list.charge_for', ['name' => 'محمد أحمد علي']).'"]')
        ->assertVisible('#entry-drawer.show')
        ->type('#entry-search', '2*رز 5')
        ->assertSee('أرز الضحى 5 كجم')
        ->keys('#entry-search', 'Enter')
        ->type('#entry-search', 'زيت كرستال')
        ->assertVisible('#entry-search-results [role="option"]')
        ->keys('#entry-search', 'Enter')
        ->type('#entry-search', '3* اوريو')
        ->assertVisible('#entry-search-results [role="option"]')
        ->keys('#entry-search', 'Enter')
        ->assertSee(__('accounts::module.entry.save_charge', ['amount' => '435.00']))
        ->keys('#entry-search', 'Control+Enter')
        ->assertSee(__('accounts::module.entry.posted_charge', ['name' => 'محمد أحمد علي', 'amount' => '435.00', 'balance' => __('accounts::module.balance.owes').' 1,335.00']))
        ->assertNoJavaScriptErrors();

    expect(CustomerAccount::query()->where('customer_id', $customer->id)->firstOrFail()->outstanding->toDecimalString())->toBe('1335.00');
});

test('a name typed on the wrong keyboard layout still finds the product', function () {
    counterWithCustomers();

    // «.dj» is «زيت» typed with the keyboard still on English.
    counterPage('ar', 'light')
        ->click('#customer-rows [data-row]:first-child [data-row-key="+"]')
        ->assertVisible('#entry-drawer.show')
        ->type('#entry-search', '.dj')
        ->assertSee('زيت كريستال 1 لتر');
});

test('a shelf pressed while the search box has the focus opens its products, and they stay open', function () {
    counterWithCustomers();
    $rice = Category::query()->where('name', 'أرز ومكرونة')->value('id');

    // The box has the focus when the drawer opens, and pressing a shelf takes
    // it for a moment; the list the shelf opens has to outlive that moment.
    $page = counterPage('ar', 'light')
        ->click('#customer-rows [data-row]:first-child [data-row-key="+"]')
        ->assertVisible('#entry-drawer.show')
        ->assertScript('document.activeElement.id', 'entry-search')
        ->click("#entry-drawer [data-picker-group=\"{$rice}\"]");

    $page->script('() => new Promise((resolve) => setTimeout(resolve, 400))');

    $page->assertVisible('#entry-search-results [role="option"] >> nth=0')
        ->assertSee('أرز الضحى 5 كجم')
        ->assertScript('document.activeElement.id', 'entry-search')
        ->assertNoJavaScriptErrors();
});

/**
 * Press a key on the list's first row, and say whether anything was clicked
 * for it — the click itself is swallowed, so nothing opens or navigates.
 */
function pressOnFirstRow(mixed $page, string $key, string $code): bool
{
    return $page->script('() => {
        const row = document.querySelector("#customer-rows [data-row]")
        let clicked = false
        const swallow = (event) => { clicked = true; event.preventDefault(); event.stopImmediatePropagation() }

        window.addEventListener("click", swallow, true)
        row.focus()
        row.dispatchEvent(new KeyboardEvent("keydown", { key: '.json_encode($key).', code: '.json_encode($code).', bubbles: true, cancelable: true }))
        window.removeEventListener("click", swallow, true)

        return clicked
    }');
}

test('shortcuts turned off in Settings: no hints, no keys, and it lasts until turned on', function () {
    counterWithCustomers();

    $hintsHidden = '() => { const hints = [...document.querySelectorAll(".kbd-hint")]; return hints.length > 0 && hints.every((hint) => getComputedStyle(hint).display === "none") }';

    $page = visit(route('admin.settings', ['tab' => 'appearance'], absolute: false))
        ->assertChecked('#shortcuts-toggle')
        ->click('#shortcuts-toggle')
        ->assertScript('document.documentElement.dataset.shortcuts', 'off')
        ->navigate(route('admin.accounts.index', absolute: false))
        ->assertScript($hintsHidden)
        ->keys('.app-content', '/')
        ->assertScript('document.activeElement.id === "customer-search"', false);

    // A row's + is a shortcut, and is off; Enter on a row is how any list
    // opens, and still answers.
    expect(pressOnFirstRow($page, '+', 'Equal'))->toBeFalse()
        ->and(pressOnFirstRow($page, 'Enter', 'Enter'))->toBeTrue();

    // Remembered by this browser, and applied before the page is painted.
    $page->refresh()->assertScript('document.documentElement.dataset.shortcuts', 'off');

    $page->navigate(route('admin.settings', ['tab' => 'appearance'], absolute: false))
        ->assertNotChecked('#shortcuts-toggle')
        ->click('#shortcuts-toggle')
        ->navigate(route('admin.accounts.index', absolute: false))
        ->assertScript($hintsHidden, false)
        ->keys('.app-content', '/')
        ->assertScript('document.activeElement.id', 'customer-search')
        ->assertNoJavaScriptErrors();

    expect(pressOnFirstRow($page, '+', 'Equal'))->toBeTrue();
});

test('the list and its drawer fit a 375px phone', function () {
    counterWithCustomers();

    $page = counterPage('ar', 'light')->on()->mobile();

    expect($page->script('() => document.documentElement.scrollWidth - document.documentElement.clientWidth'))->toBeLessThanOrEqual(0);

    $page->click('#customer-rows [data-row]:first-child [data-row-key="+"]')->assertVisible('#entry-drawer.show');

    expect($page->script('() => document.documentElement.scrollWidth - document.documentElement.clientWidth'))->toBeLessThanOrEqual(0);
});

/**
 * The owing customer's account with a statement worth reading: lines, a
 * payment, a void, a correction, an opening balance.
 */
function accountWithHistory(): CustomerData
{
    ['owing' => $customer] = counterWithCustomers();
    $by = AdminUser::query()->firstOrFail()->getKey();
    $ledger = app(AccountLedgerInterface::class);

    app(RecordOpeningBalance::class)->handle($customer->id, $by, (string) Str::uuid(), Money::fromDecimalString('300.00'), TransactionType::Debit, 'من الكشكول');
    $wrong = $ledger->charge(new ChargeRequest($customer->id, $by, (string) Str::uuid(), [ChargeLine::free('حاجات', Money::fromDecimalString('450.00'))]))->first()->entryId;
    app(CorrectEntry::class)->handle($wrong, $by, (string) Str::uuid(), CorrectionReason::WrongAmount, lines: [ChargeLine::free('حاجات', Money::fromDecimalString('400.00'))]);
    $ledger->payment(new PaymentRequest($customer->id, $by, (string) Str::uuid(), Money::fromDecimalString('200.00')));
    $undone = $ledger->charge(new ChargeRequest($customer->id, $by, (string) Str::uuid(), [ChargeLine::free('حاجات', Money::fromDecimalString('35.00'))]))->first()->entryId;
    $ledger->void(new VoidRequest($undone, $by, (string) Str::uuid(), CorrectionReason::Undo));

    return $customer;
}

function accountPage(CustomerData $customer, string $language, string $mode): mixed
{
    test()->withCookie(Area::Admin->localeCookie(), $language);

    $page = visit(route('admin.accounts.show', $customer->id, absolute: false));

    return $mode === 'dark' ? $page->inDarkMode() : $page->inLightMode();
}

test('the account page, with a statement on it, passes axe', function (string $language, string $mode) {
    $customer = accountWithHistory();

    accountPage($customer, $language, $mode)
        ->assertSee('محمد أحمد علي')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
})->with(['ar', 'en'])->with(['light', 'dark']);

test('an entry\'s details, open, pass axe', function (string $language, string $mode) {
    $customer = accountWithHistory();

    accountPage($customer, $language, $mode)
        ->click('#statement-rows [data-row] .statement-title >> nth=0')
        ->assertVisible('#entry-details.show')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
})->with(['ar', 'en'])->with(['light', 'dark']);

test('the account page and an entry\'s details fit a 375px phone', function () {
    $customer = accountWithHistory();

    $page = accountPage($customer, 'ar', 'light')->on()->mobile();

    expect($page->script('() => document.documentElement.scrollWidth - document.documentElement.clientWidth'))->toBeLessThanOrEqual(0);

    $page->click('#statement-rows [data-row] .statement-title >> nth=0')->assertVisible('#entry-details.show');

    expect($page->script('() => document.documentElement.scrollWidth - document.documentElement.clientWidth'))->toBeLessThanOrEqual(0);
});

test('on the account page, + opens a charge for this customer', function () {
    $customer = accountWithHistory();

    accountPage($customer, 'ar', 'light')
        ->keys('.app-content', '+')
        ->assertVisible('#entry-drawer.show')
        ->assertScript('document.activeElement.id', 'entry-search');
});
