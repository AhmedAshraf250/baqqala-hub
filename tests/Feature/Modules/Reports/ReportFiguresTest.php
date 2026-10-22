<?php

use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\AdminUser;
use App\Foundation\Money\Money;
use App\Foundation\Settings\SettingStore;
use App\Foundation\Time\LocalClock;
use App\Modules\Accounts\Contracts\AccountLedgerInterface;
use App\Modules\Accounts\Contracts\Data\ChargeLine;
use App\Modules\Accounts\Contracts\Data\ChargeRequest;
use App\Modules\Accounts\Contracts\Data\PaymentRequest;
use App\Modules\Accounts\Contracts\Data\VoidRequest;
use App\Modules\Accounts\Contracts\Enums\CorrectionReason;
use App\Modules\Accounts\Contracts\Enums\OverpaymentChoice;
use App\Modules\Accounts\Domain\Actions\CorrectEntry;
use App\Modules\Catalog\Contracts\Enums\SaleUnit;
use App\Modules\Catalog\Database\Models\Category;
use App\Modules\Catalog\Database\Models\Product;
use App\Modules\Customers\Contracts\CustomerGroupRepositoryInterface;
use App\Modules\Customers\Contracts\Data\CustomerGroupData;
use App\Modules\Customers\Contracts\Enums\GroupColor;
use App\Modules\Customers\Domain\Actions\CreateCustomer;
use App\Modules\Reports\Domain\Queries\ActivityReport;
use App\Modules\Reports\Domain\Queries\AgingReport;
use App\Modules\Reports\Domain\Queries\CorrectionsReport;
use App\Modules\Reports\Domain\Queries\DebtsReport;
use App\Modules\Reports\Domain\Queries\ProductsReport;
use App\Modules\Reports\Domain\Queries\ReportPeriod;
use App\Modules\Reports\Domain\Queries\StaffReport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

/**
 * A shop's year, told in Cairo time, up to 2 October at 18:00:
 *
 *  - Karim took 400 on 20 June and has paid nothing since.
 *  - Nadia took two bags of rice (300) on 15 August and paid 100 on 20 August.
 *  - Laila took 100 on 10 September and paid it all the same day.
 *  - Hoda paid 30 ahead on 25 September.
 *  - On 1 October Sara charged Mohamed 350: a bag of rice, 1.25 kg of cheese,
 *    and 25 of odds and ends.
 *  - On 2 October Omar charged Mohamed 90 by mistake and undid it at once,
 *    then took 50 from him — which Sara corrected to 70.
 *
 * Mohamed and Nadia are «الجيران»; nobody else is in a group.
 */
beforeEach(function () {
    $this->owner = actingAsAdmin();
    $this->sara = AdminUser::factory()->create(['name' => 'سارة']);
    $this->omar = AdminUser::factory()->create(['name' => 'عمر']);

    $create = app(CreateCustomer::class);
    $this->mohamed = $create->handle(['name' => 'محمد أحمد', 'phone' => '01001234567']);
    $this->nadia = $create->handle(['name' => 'نادية سمير', 'phone' => '01119876543']);
    $this->karim = $create->handle(['name' => 'كريم حسن']);
    $this->laila = $create->handle(['name' => 'ليلى فؤاد']);
    $this->hoda = $create->handle(['name' => 'هدى علي']);

    $groups = app(CustomerGroupRepositoryInterface::class);
    $neighbours = $groups->save(new CustomerGroupData(null, 'الجيران', GroupColor::Teal, createdBy: $this->owner->getKey()));
    $groups->saveGroupMembers($neighbours->id, [$this->mohamed->id, $this->nadia->id], true, $this->owner->getKey());

    $this->grains = Category::factory()->create(['name' => 'أرز ومكرونة']);
    $this->dairy = Category::factory()->create(['name' => 'ألبان وبيض']);
    $this->rice = Product::factory()->for($this->grains)->priced('150.00')->create(['name' => 'أرز الضحى 5 كجم']);
    $this->cheese = Product::factory()->for($this->dairy)->byWeight()->priced('140.00')->create(['name' => 'جبنة بيضاء سائبة']);

    $ledger = app(AccountLedgerInterface::class);
    $at = fn (string $moment) => $this->travelTo(CarbonImmutable::parse("2026-{$moment}", 'Africa/Cairo'));
    $charge = fn ($customer, AdminUser $by, array $lines) => $ledger->charge(new ChargeRequest($customer->id, $by->getKey(), (string) Str::uuid(), $lines))->first()->entryId;
    $pay = fn ($customer, AdminUser $by, string $amount) => $ledger->payment(new PaymentRequest($customer->id, $by->getKey(), (string) Str::uuid(), Money::fromDecimalString($amount), overpayment: OverpaymentChoice::KeepAsCredit))->first()->entryId;
    $odds = fn (string $amount) => ChargeLine::free('طلبات', Money::fromDecimalString($amount));

    $at('06-20 10:00');
    $charge($this->karim, $this->sara, [$odds('400.00')]);

    $at('08-15 10:00');
    $charge($this->nadia, $this->sara, [ChargeLine::product($this->rice->id, 2000)]);
    $at('08-20 10:00');
    $pay($this->nadia, $this->sara, '100.00');

    $at('09-10 10:00');
    $charge($this->laila, $this->sara, [$odds('100.00')]);
    $at('09-10 11:00');
    $pay($this->laila, $this->sara, '100.00');
    $at('09-25 10:00');
    $pay($this->hoda, $this->sara, '30.00');

    $at('10-01 21:00');
    $charge($this->mohamed, $this->sara, [ChargeLine::product($this->rice->id, 1000), ChargeLine::product($this->cheese->id, 1250), $odds('25.00')]);

    $at('10-02 09:15');
    $mistake = $charge($this->mohamed, $this->omar, [$odds('90.00')]);
    $ledger->void(new VoidRequest($mistake, $this->omar->getKey(), (string) Str::uuid(), CorrectionReason::Undo));

    $at('10-02 10:00');
    $short = $pay($this->mohamed, $this->omar, '50.00');
    $at('10-02 12:00');
    app(CorrectEntry::class)->handle($short, $this->sara->getKey(), (string) Str::uuid(), CorrectionReason::WrongAmount, amount: Money::fromDecimalString('70.00'));

    $at('10-02 18:00');
});

function reportPeriod(string $name, string $from = '', string $to = ''): ReportPeriod
{
    return ReportPeriod::named($name, app(LocalClock::class), $from, $to);
}

/**
 * @param  array<string, mixed>  $row
 * @return array<string, mixed>
 */
function figuresOf(array $row): array
{
    return array_map(fn (mixed $value) => $value instanceof Money ? $value->toDecimalString() : $value, $row);
}

test('what is owed now: owing, held, and settled, from every account', function () {
    expect(figuresOf(app(DebtsReport::class)->totals()))->toBe([
        'accounts' => 5,
        'owing' => 3,
        // Karim 400, Mohamed 280 (350, less 70 — the voided 90 and the corrected 50 count for nothing), Nadia 200.
        'owed' => '880.00',
        'inCredit' => 1,
        'held' => '30.00',
        'settled' => 1,
    ]);
});

test('what each group owes, and those in no group last', function () {
    expect(array_map(figuresOf(...), app(DebtsReport::class)->byGroup()))->toBe([
        ['name' => 'الجيران', 'color' => 'teal', 'members' => 2, 'owing' => 2, 'owed' => '480.00'],
        ['name' => null, 'color' => null, 'members' => 3, 'owing' => 1, 'owed' => '400.00'],
    ]);
});

test('who owes most, with when they last paid', function () {
    $largest = app(DebtsReport::class)->largest();

    expect(array_map(fn (array $row) => [$row['name'], $row['owed']->toDecimalString()], $largest))
        ->toBe([['كريم حسن', '400.00'], ['محمد أحمد', '280.00'], ['نادية سمير', '200.00']])
        ->and($largest[0]['lastPayment'])->toBeNull()
        ->and($largest[2]['lastPayment']->equalTo(CarbonImmutable::parse('2026-08-20 10:00', 'Africa/Cairo')))->toBeTrue();
});

test('how long each debtor has gone without paying, bucket by bucket', function () {
    expect(array_map(figuresOf(...), app(AgingReport::class)->buckets()))->toBe([
        'under_30' => ['count' => 1, 'owed' => '280.00'],
        // Nadia owes since 15 August, but paid on the 20th: 43 days.
        'under_60' => ['count' => 1, 'owed' => '200.00'],
        'under_90' => ['count' => 0, 'owed' => '0.00'],
        'over_90' => ['count' => 1, 'owed' => '400.00'],
    ]);
});

test('the call list puts the longest without paying first, and narrows to a bucket', function () {
    $aging = app(AgingReport::class);

    expect(array_column($aging->customers(null), 'name'))->toBe(['كريم حسن', 'نادية سمير', 'محمد أحمد'])
        ->and(array_column($aging->customers('under_60'), 'name'))->toBe(['نادية سمير'])
        ->and($aging->customers('under_90'))->toBe([]);
});

test('a period\'s rows, day by day, count only what stands', function () {
    $activity = app(ActivityReport::class);
    $month = reportPeriod('month');

    expect(array_map(figuresOf(...), $activity->rows($month)))->toBe([
        ['label' => $month->steps(app(LocalClock::class))[0]['label'], 'taken' => '350.00', 'charges' => 1, 'collected' => '0.00', 'payments' => 0],
        // Omar's undone 90 and his corrected 50 are gone; Sara's 70 stands.
        ['label' => $month->steps(app(LocalClock::class))[1]['label'], 'taken' => '0.00', 'charges' => 0, 'collected' => '70.00', 'payments' => 1],
    ])->and(figuresOf($activity->totals($month)))->toBe(['taken' => '350.00', 'charges' => 1, 'collected' => '70.00', 'payments' => 1]);
});

test('which way debts moved is said in words, and by how much in figures', function () {
    $month = Livewire::test('reports::admin.page.activity')->instance();
    $september = Livewire::withQueryParams(['period' => 'last_month'])->test('reports::admin.page.activity')->instance();

    expect([$month->movement['label'], $month->movement['amount']->toDecimalString()])->toBe([__('reports::module.activity.rose'), '280.00'])
        // Laila took 100 and paid 100; Hoda paid 30 ahead.
        ->and([$september->movement['label'], $september->movement['amount']->toDecimalString()])->toBe([__('reports::module.activity.fell'), '30.00'])
        ->and($month->change(Money::fromDecimalString('10.00'), Money::fromDecimalString('10.00')))->toBe(__('reports::module.activity.none'));
});

test('a long period is cut into months', function () {
    $year = reportPeriod('year');
    $rows = collect(app(ActivityReport::class)->rows($year))->map(figuresOf(...));

    expect($year->byMonth())->toBeTrue()
        ->and($rows)->toHaveCount(10)
        ->and($rows->pluck('taken')->all())->toBe(['0.00', '0.00', '0.00', '0.00', '0.00', '400.00', '0.00', '300.00', '100.00', '350.00'])
        ->and($rows->pluck('collected')->all())->toBe(['0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '100.00', '130.00', '70.00']);
});

test('an entry made before the business day starts counts towards the day before', function () {
    app(SettingStore::class)->put(LocalClock::DAY_STARTS_AT, '03:00');
    $this->travelTo(CarbonImmutable::parse('2026-10-03 01:30', 'Africa/Cairo'));
    app(AccountLedgerInterface::class)->charge(new ChargeRequest($this->laila->id, $this->sara->getKey(), (string) Str::uuid(), [ChargeLine::free('طلبات', Money::fromDecimalString('15.00'))]));

    $rows = app(ActivityReport::class)->rows(reportPeriod('month'));

    expect($rows)->toHaveCount(2)
        ->and($rows[1]['taken']->toDecimalString())->toBe('15.00');
});

test('a period of its own takes its dates either way round, and never runs past today', function () {
    $custom = reportPeriod('custom', '2026-10-30', '2026-09-01');

    expect($custom->firstDay->toDateString())->toBe('2026-09-01')
        ->and($custom->lastDay->toDateString())->toBe('2026-10-02')
        ->and(reportPeriod('last_month')->firstDay->toDateString())->toBe('2026-09-01')
        ->and(reportPeriod('last_month')->lastDay->toDateString())->toBe('2026-09-30')
        ->and(reportPeriod('custom', 'yesterday')->firstDay->toDateString())->toBe('2026-10-01');
});

test('each person\'s share: the cash they collected, and what they put right', function () {
    expect(array_map(figuresOf(...), app(StaffReport::class)->people(reportPeriod('month'))))->toBe([
        ['id' => $this->sara->getKey(), 'name' => 'سارة', 'charges' => 1, 'taken' => '350.00', 'payments' => 1, 'collected' => '70.00', 'undone' => 0, 'voided' => 0, 'corrected' => 1],
        ['id' => $this->omar->getKey(), 'name' => 'عمر', 'charges' => 0, 'taken' => '0.00', 'payments' => 0, 'collected' => '0.00', 'undone' => 1, 'voided' => 0, 'corrected' => 0],
    ]);
});

test('what was put right, by reason and one by one', function () {
    $corrections = app(CorrectionsReport::class);
    $entries = $corrections->entries(reportPeriod('month'));

    expect($corrections->byReason(reportPeriod('month')))->toMatchArray(['undo' => 1, 'wrong_amount' => 1, 'duplicate' => 0])
        ->and(array_map(fn (array $entry) => [$entry['kind']->value, $entry['amount']->toDecimalString(), $entry['reason'], $entry['recordedBy'], $entry['by'], $entry['corrected']], $entries))
        ->toBe([
            ['payment', '50.00', CorrectionReason::WrongAmount, 'عمر', 'سارة', true],
            ['charge', '90.00', CorrectionReason::Undo, 'عمر', 'عمر', false],
        ])
        ->and($corrections->entries(reportPeriod('month'), CorrectionReason::Undo))->toHaveCount(1)
        ->and(array_sum($corrections->byReason(reportPeriod('last_month'))))->toBe(0);
});

test('what was taken on account most, product by product, the lines with no product last', function () {
    expect(array_map(figuresOf(...), app(ProductsReport::class)->products(reportPeriod('month'))))->toBe([
        ['productId' => $this->cheese->id, 'name' => 'جبنة بيضاء سائبة', 'unit' => 'kg', 'category' => 'ألبان وبيض', 'quantity' => 1250, 'amount' => '175.00', 'charges' => 1],
        ['productId' => $this->rice->id, 'name' => 'أرز الضحى 5 كجم', 'unit' => 'piece', 'category' => 'أرز ومكرونة', 'quantity' => 1000, 'amount' => '150.00', 'charges' => 1],
        // The undone 90 of odds and ends is not here.
        ['productId' => null, 'name' => null, 'unit' => null, 'category' => null, 'quantity' => 0, 'amount' => '25.00', 'charges' => 1],
    ]);

    $year = app(ProductsReport::class)->products(reportPeriod('year'));

    expect([$year[0]['name'], $year[0]['quantity'], $year[0]['amount']->toDecimalString(), $year[0]['charges']])->toBe(['أرز الضحى 5 كجم', 3000, '450.00', 2]);
});

test('what each shelf came to', function () {
    expect(array_map(figuresOf(...), app(ProductsReport::class)->categories(reportPeriod('month'))))->toBe([
        ['category' => 'ألبان وبيض', 'amount' => '175.00', 'lines' => 1],
        ['category' => 'أرز ومكرونة', 'amount' => '150.00', 'lines' => 1],
        ['category' => null, 'amount' => '25.00', 'lines' => 1],
    ]);
});

test('a quantity reads in its unit, without trailing zeros', function () {
    $products = Livewire::test('reports::admin.page.products')->instance();

    expect($products->quantity(1250, 'kg'))->toBe('1.25 '.SaleUnit::Kilogram->label())
        ->and($products->quantity(3000, 'piece'))->toBe('3 '.SaleUnit::Piece->label())
        ->and($products->quantity(500, 'kg'))->toBe('0.5 '.SaleUnit::Kilogram->label());
});

test('the reports write nothing', function () {
    $before = DB::table('account_transactions')->count().'/'.DB::table('customer_accounts')->sum('outstanding');

    foreach (['index', 'debts', 'aging', 'activity', 'staff', 'corrections', 'products'] as $report) {
        Livewire::test("reports::admin.page.{$report}")->assertOk();
    }

    expect(DB::table('account_transactions')->count().'/'.DB::table('customer_accounts')->sum('outstanding'))->toBe($before);
});

test('each report shows its figures', function (string $report, array $seen) {
    $page = Livewire::test("reports::admin.page.{$report}");

    foreach ($seen as $text) {
        $page->assertSeeText($text);
    }
})->with([
    'debts' => ['debts', ['880.00', 'الجيران', 'كريم حسن', '280.00']],
    'aging' => ['aging', ['كريم حسن', 'نادية سمير', '01119876543']],
    'activity' => ['activity', ['350.00', '70.00']],
    'staff' => ['staff', ['سارة', 'عمر', '70.00']],
    'corrections' => ['corrections', ['محمد أحمد', '50.00', 'عمر']],
    'products' => ['products', ['جبنة بيضاء سائبة', '175.00', 'أرز ومكرونة']],
]);

test('the buckets grow louder the longer they run: pale at first, solid only at the far end', function () {
    Livewire::test('reports::admin.page.aging')
        ->assertSeeHtml('bg-secondary-subtle text-secondary-emphasis')
        ->assertSeeHtml('bg-warning-subtle text-warning-emphasis')
        ->assertSeeHtml('text-bg-warning')
        ->assertSeeHtml('text-bg-danger');
});

test('the call list dials each debtor who has a number', function () {
    Livewire::test('reports::admin.page.aging')
        ->assertSeeHtml('href="tel:01119876543"')
        ->assertSeeHtml('href="tel:01001234567"')
        ->call('choose', 'over_90')
        ->assertSet('bucket', 'over_90')
        ->assertDontSeeHtml('href="tel:01119876543"')
        ->assertSeeText('كريم حسن')
        // Chosen twice is everyone again; nonsense is everyone too.
        ->call('choose', 'over_90')
        ->assertSet('bucket', '')
        ->call('choose', 'nonsense')
        ->assertSet('bucket', '');
});

test('the corrections narrow to one reason', function () {
    Livewire::test('reports::admin.page.corrections')
        ->call('chooseReason', 'undo')
        ->assertSet('reason', 'undo')
        ->assertSeeText('90.00')
        ->assertDontSeeText('50.00');
});

test('only the reasons that came up are offered, and the one chosen stays to be let go', function () {
    Livewire::test('reports::admin.page.corrections')
        ->assertSeeText(CorrectionReason::WrongAmount->label())
        ->assertDontSeeText(CorrectionReason::Duplicate->label());

    Livewire::withQueryParams(['reason' => 'duplicate'])
        ->test('reports::admin.page.corrections')
        ->assertSeeText(CorrectionReason::Duplicate->label())
        ->assertSeeText(__('reports::module.corrections.none'));
});

test('each person\'s fixes name only what there was', function () {
    Livewire::test('reports::admin.page.staff')
        ->assertSeeText(trans_choice('reports::module.staff.undone', 1, ['count' => 1]))
        ->assertSeeText(trans_choice('reports::module.staff.corrected', 1, ['count' => 1]))
        ->assertDontSeeText(trans_choice('reports::module.staff.voided', 0, ['count' => 0]));
});

test('the period is kept in the address, and a period nobody offers is this month', function () {
    Livewire::withQueryParams(['period' => 'last_month'])
        ->test('reports::admin.page.activity')
        ->assertSet('period', 'last_month')
        ->assertSeeText('100.00');

    expect(Livewire::withQueryParams(['period' => 'forever'])->test('reports::admin.page.activity')->instance()->range->firstDay->toDateString())
        ->toBe('2026-10-01');
});

test('the reports index leads each report with its figure', function () {
    Livewire::test('reports::admin.page.index')
        ->assertSeeText('880.00')
        ->assertSeeText('سارة')
        ->assertSeeText('جبنة بيضاء سائبة')
        ->assertSeeText(__('reports::module.aging.title'));
});

test('only those who may read reports reach them', function (string $route) {
    $cashier = AdminUser::factory()->create();
    $cashier->syncRoles('cashier');
    $this->actingAs($cashier, Area::Admin->guard());

    $this->get(route($route))->assertForbidden();
})->with(['admin.reports.index', 'admin.reports.debts', 'admin.reports.aging', 'admin.reports.activity', 'admin.reports.staff', 'admin.reports.corrections', 'admin.reports.products']);
