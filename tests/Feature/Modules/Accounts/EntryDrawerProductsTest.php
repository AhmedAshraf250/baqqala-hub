<?php

use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\AdminUser;
use App\Foundation\Money\Money;
use App\Modules\Accounts\Contracts\AccountLedgerInterface;
use App\Modules\Accounts\Contracts\Data\ChargeLine;
use App\Modules\Accounts\Contracts\Data\ChargeRequest;
use App\Modules\Accounts\Database\Models\AccountTransaction;
use App\Modules\Accounts\Database\Models\AccountTransactionItem;
use App\Modules\Accounts\Database\Models\CustomerAccount;
use App\Modules\Catalog\Database\Models\Category;
use App\Modules\Catalog\Database\Models\Product;
use App\Modules\Customers\Domain\Actions\CreateCustomer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->admin = actingAsAdmin();
    $this->customer = app(CreateCustomer::class)->handle(['name' => 'أحمد سامي']);

    $this->rice = Category::factory()->create(['name' => 'أرز ومكرونة', 'sort_order' => 10]);
    $this->oils = Category::factory()->create(['name' => 'زيوت وسمنة', 'sort_order' => 20]);
    $this->dairy = Category::factory()->create(['name' => 'ألبان وبيض', 'sort_order' => 30]);

    $this->duhaRice = Product::factory()->for($this->rice)->priced('150.00')->create(['name' => 'أرز الضحى 5 كجم']);
    $this->crystalOil = Product::factory()->for($this->oils)->priced('90.00')->create(['name' => 'زيت كريستال 1 لتر', 'search_terms' => 'crystal']);
    $this->oreo = Product::factory()->for($this->rice)->priced('15.00')->create(['name' => 'بسكويت أوريو']);
    $this->cheese = Product::factory()->for($this->dairy)->byWeight()->priced('140.00')->create(['name' => 'جبنة بيضاء سائبة']);
});

function productDrawer(): Testable
{
    return Livewire::test('accounts::admin.entry-drawer')->dispatch('open-entry', customerId: test()->customer->id, kind: 'charge');
}

test('the picker is handed the active catalog, folded for search, with its shelves', function () {
    Product::factory()->for($this->oils)->inactive()->create(['name' => 'Archived oil']);

    $catalog = productDrawer()->instance()->pickerCatalog();
    $items = collect($catalog['items'])->keyBy('label');

    expect($items)->toHaveCount(4)
        ->and($items->has('Archived oil'))->toBeFalse()
        ->and($items['زيت كريستال 1 لتر'])->toMatchArray([
            'id' => $this->crystalOil->id,
            'aside' => '90.00',
            'group' => $this->oils->id,
            'detail' => 'زيوت وسمنة · '.__('catalog::module.unit.piece'),
        ])
        // Its other names, and its shelf's words, are searched too.
        ->and($items['زيت كريستال 1 لتر']['terms'])->toContain('crystal')->toContain('زيوت')
        ->and(collect($catalog['groups'])->pluck('count', 'label')->all())->toBe(['أرز ومكرونة' => 2, 'زيوت وسمنة' => 1, 'ألبان وبيض' => 1]);
});

test('the picker only says it is current when the version it holds still is', function () {
    $drawer = productDrawer();
    $version = $drawer->instance()->pickerCatalog()['version'];

    expect($drawer->instance()->pickerCatalog($version))->toBe(['version' => $version, 'fresh' => true]);

    $this->crystalOil->update(['price' => Money::fromDecimalString('95.00')]);

    expect($drawer->instance()->pickerCatalog($version))->toHaveKey('items');
});

test('the catalog is only for whoever may record', function () {
    $viewer = AdminUser::factory()->create();
    $viewer->givePermissionTo('accounts.view');
    test()->actingAs($viewer, Area::Admin->guard());

    Livewire::test('accounts::admin.entry-drawer')->call('pickerCatalog')->assertForbidden();
});

test('your example: two of the rice, a crystal oil, three oreos, and Ctrl+Enter', function () {
    // «2*رز 5» Enter, «زيت كر» Enter, «3* اوريو» Enter, Ctrl+Enter — 435.00.
    productDrawer()
        ->call('addProduct', $this->duhaRice->id, '2')
        ->call('addProduct', $this->crystalOil->id)
        ->call('addProduct', $this->oreo->id, '3')
        ->assertSeeText(__('accounts::module.entry.save_charge', ['amount' => '435.00']))
        ->call('save')
        ->assertHasNoErrors();

    $items = AccountTransactionItem::query()->orderBy('position')->get();

    expect(CustomerAccount::query()->where('customer_id', $this->customer->id)->value('outstanding')->toDecimalString())->toBe('435.00')
        ->and($items->pluck('name')->all())->toBe(['أرز الضحى 5 كجم', 'زيت كريستال 1 لتر', 'بسكويت أوريو'])
        ->and($items->pluck('quantity')->all())->toBe([2000, 1000, 3000])
        ->and($items->map(fn ($item) => $item->line_total->toDecimalString())->all())->toBe(['300.00', '90.00', '45.00']);
});

test('the same product chosen twice is one line with more of it', function () {
    productDrawer()
        ->call('addProduct', $this->oreo->id)
        ->call('addProduct', $this->oreo->id, '2')
        ->assertCount('form.lines', 1)
        ->assertSet('form.lines.0.quantity', '3');
});

test('a price changed at the counter is kept beside the catalog\'s, and shows', function () {
    productDrawer()
        ->call('addProduct', $this->crystalOil->id)
        ->set('form.lines.0.price', '85')
        ->assertSeeText(__('accounts::module.entry.original_price', ['price' => '90.00']))
        ->call('save')
        ->assertHasNoErrors();

    $item = AccountTransactionItem::query()->sole();

    expect($item->unit_price->toDecimalString())->toBe('85.00')
        ->and($item->list_price->toDecimalString())->toBe('90.00');
});

test('a weighed product is weighed, or sold by what it costs', function () {
    $drawer = productDrawer()->call('addProduct', $this->cheese->id);

    $key = $drawer->get('form.lines.0.key');

    $drawer->call('setQuantity', $key, '0.25')
        ->assertSet('form.lines.0.quantity', '0.25')
        ->assertSeeText(__('accounts::module.entry.save_charge', ['amount' => '35.00']))
        ->call('sellByAmount', $key, true)
        ->set('form.lines.0.amount', '10')
        ->assertSeeText(__('accounts::module.entry.about', ['quantity' => '0.071 '.__('catalog::module.unit.kg')]))
        ->call('save')
        ->assertHasNoErrors();

    $item = AccountTransactionItem::query()->sole();

    expect($item->line_total->toDecimalString())->toBe('10.00')
        ->and($item->quantity)->toBe(71);
});

test('a quantity that is not one is refused under the line', function (string $quantity) {
    productDrawer()
        ->call('addProduct', $this->oreo->id)
        ->set('form.lines.0.quantity', $quantity)
        ->call('save')
        ->assertHasErrors(['form.lines.0.quantity']);
})->with(['zero' => ['0'], 'a fraction of a piece' => ['1.5'], 'letters' => ['abc']]);

test('a price changed in the catalog after it was picked is turned back', function () {
    $drawer = productDrawer()->call('addProduct', $this->crystalOil->id);

    $this->crystalOil->update(['price' => Money::fromDecimalString('95.00')]);

    $drawer->call('save')
        ->assertSet('problem', __('accounts::module.entry.price_changed', ['from' => '90.00', 'to' => '95.00']));

    expect(AccountTransaction::query()->count())->toBe(0);
});

test('a product archived after it was picked is turned back', function () {
    $drawer = productDrawer()->call('addProduct', $this->oreo->id);

    $this->oreo->update(['is_active' => false]);

    $drawer->call('save')->assertSet('problem', __('accounts::module.entry.unavailable'));

    expect(AccountTransaction::query()->count())->toBe(0);
});

test('the drawer suggests what this customer usually takes, or else what the shop sells most', function () {
    $ledger = app(AccountLedgerInterface::class);
    $by = $this->admin->getKey();
    $neighbour = app(CreateCustomer::class)->handle(['name' => 'Neighbour']);

    // The shop sells the cheese most; this customer takes the rice.
    foreach (range(1, 3) as $time) {
        $ledger->charge(new ChargeRequest($neighbour->id, $by, (string) Str::uuid(), [ChargeLine::product($this->cheese->id, 500)]));
    }

    productDrawer()->assertSeeText(__('accounts::module.entry.popular'))->assertSeeText('جبنة بيضاء سائبة');

    $ledger->charge(new ChargeRequest($this->customer->id, $by, (string) Str::uuid(), [ChargeLine::product($this->duhaRice->id, 1000)]));

    $usual = productDrawer()
        ->assertSeeText(__('accounts::module.entry.usual', ['name' => 'أحمد سامي']))
        ->instance()->usual['products'];

    expect(array_map(fn ($product) => $product->id, $usual))->toBe([$this->duhaRice->id]);
});

test('a suggestion is one press', function () {
    $ledger = app(AccountLedgerInterface::class);
    $ledger->charge(new ChargeRequest($this->customer->id, $this->admin->getKey(), (string) Str::uuid(), [ChargeLine::product($this->oreo->id, 1000)]));

    productDrawer()
        ->assertSeeHtml('wire:click="addProduct('.$this->oreo->id.')"')
        ->call('addProduct', $this->oreo->id)
        ->assertSet('form.lines.0.name', 'بسكويت أوريو');
});
