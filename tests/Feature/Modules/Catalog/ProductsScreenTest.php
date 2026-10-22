<?php

use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\AdminUser;
use App\Foundation\Money\Money;
use App\Modules\Accounts\Contracts\AccountLedgerInterface;
use App\Modules\Accounts\Contracts\Data\ChargeLine;
use App\Modules\Accounts\Contracts\Data\ChargeRequest;
use App\Modules\Catalog\Contracts\Enums\SaleUnit;
use App\Modules\Catalog\Database\Models\Category;
use App\Modules\Catalog\Database\Models\Product;
use App\Modules\Customers\Domain\Actions\CreateCustomer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->admin = actingAsAdmin();

    $this->oils = Category::factory()->create(['name' => 'زيوت وسمنة', 'sort_order' => 20]);
    $this->rice = Category::factory()->create(['name' => 'أرز ومكرونة', 'sort_order' => 10]);

    $this->crystal = Product::factory()->for($this->oils)->priced('90.00')->create(['name' => 'زيت كريستال 1 لتر', 'search_terms' => 'crystal كرستال', 'barcode' => '6221234500017']);
    $this->loose = Product::factory()->for($this->oils)->priced('75.00')->create(['name' => 'زيت سائب', 'unit' => SaleUnit::Litre, 'search_terms' => 'زيت فرط']);
    $this->doha = Product::factory()->for($this->rice)->priced('38.00')->create(['name' => 'أرز الضحى 1 كجم']);
});

/**
 * Sign in as an administrator holding one of the seeded roles.
 */
function catalogStaff(string $role): AdminUser
{
    $user = AdminUser::factory()->create();
    $user->syncRoles($role);
    test()->actingAs($user, Area::Admin->guard());

    return $user;
}

/**
 * @return list<string>
 */
function listedProducts(mixed $screen): array
{
    return $screen->instance()->rows->getCollection()->pluck('name')->all();
}

test('the list is in shelf order, and finds a product by its name, another name, or its barcode', function () {
    $screen = Livewire::test('catalog::admin.page.products');

    expect(listedProducts($screen))->toBe(['أرز الضحى 1 كجم', 'زيت سائب', 'زيت كريستال 1 لتر']);

    expect(listedProducts($screen->set('q', 'كرستال')))->toBe(['زيت كريستال 1 لتر'])
        ->and(listedProducts($screen->set('q', 'crystal')))->toBe(['زيت كريستال 1 لتر'])
        ->and(listedProducts($screen->set('q', 'زيت فرط')))->toBe(['زيت سائب'])
        ->and(listedProducts($screen->set('q', '٦٢٢١٢٣٤٥٠٠٠١٧')))->toBe(['زيت كريستال 1 لتر'])
        ->and(listedProducts($screen->set('q', 'ارز')))->toBe(['أرز الضحى 1 كجم']);
});

test('a shelf narrows the list to it and the shelves under it', function () {
    $loose = Category::factory()->create(['name' => 'زيوت فرط', 'parent_id' => $this->oils->id]);
    Product::factory()->for($loose)->create(['name' => 'زيت ذرة فرط']);

    $screen = Livewire::test('catalog::admin.page.products')->set('category', $this->oils->id);

    expect(listedProducts($screen))->toBe(['زيت سائب', 'زيت كريستال 1 لتر', 'زيت ذرة فرط'])
        ->and($screen->instance()->shelves)->toBe([
            $this->rice->id => 'أرز ومكرونة',
            $this->oils->id => 'زيوت وسمنة',
            $loose->id => 'زيوت وسمنة › زيوت فرط',
        ]);
});

test('archived products are a list of their own, each choice counting what it holds', function () {
    $this->loose->update(['is_active' => false]);

    $screen = Livewire::test('catalog::admin.page.products');

    expect($screen->instance()->counts)->toBe(['active' => 2, 'archived' => 1])
        ->and(listedProducts($screen->set('archived', true)))->toBe(['زيت سائب']);
});

test('a price is changed where it stands, typed on either keyboard, and can be taken back', function () {
    $screen = Livewire::test('catalog::admin.page.products')
        ->call('editPrice', $this->crystal->id)
        ->assertSet('editing', $this->crystal->id)
        ->assertSet('price', '90.00')
        ->set('price', '٩٥')
        ->call('savePrice')
        ->assertHasNoErrors()
        ->assertSet('editing', null)
        ->assertSee(__('catalog::module.products.price_changed', ['name' => 'زيت كريستال 1 لتر', 'price' => '95.00', 'before' => '90.00']))
        ->assertSee(__('catalog::module.products.was', ['price' => '90.00']));

    expect($this->crystal->refresh()->price->toDecimalString())->toBe('95.00');

    $screen->call('revertPrice')->assertSet('changed', null);

    expect($this->crystal->refresh()->price->toDecimalString())->toBe('90.00');
});

test('taking a price back leaves it alone when someone changed it again since', function () {
    $screen = Livewire::test('catalog::admin.page.products')
        ->call('editPrice', $this->crystal->id)
        ->set('price', '95')
        ->call('savePrice');

    $this->crystal->refresh()->update(['price' => Money::fromDecimalString('99.00')]);

    $screen->call('revertPrice');

    expect($this->crystal->refresh()->price->toDecimalString())->toBe('99.00');
});

test('a price that is not one, or is nothing, is refused and the old one stays', function (string $typed) {
    Livewire::test('catalog::admin.page.products')
        ->call('editPrice', $this->crystal->id)
        ->set('price', $typed)
        ->call('savePrice')
        ->assertHasErrors('price')
        ->assertSet('editing', $this->crystal->id);

    expect($this->crystal->refresh()->price->toDecimalString())->toBe('90.00');
})->with(['letters' => 'تسعين', 'zero' => '0', 'negative' => '-5', 'three decimals' => '9.999', 'empty' => '']);

test('Escape leaves the price as it was', function () {
    Livewire::test('catalog::admin.page.products')
        ->call('editPrice', $this->crystal->id)
        ->set('price', '1')
        ->call('cancelPrice')
        ->assertSet('editing', null)
        ->assertSet('price', '');

    expect($this->crystal->refresh()->price->toDecimalString())->toBe('90.00');
});

test('a cashier sees the catalog and its prices, and changes none of them', function () {
    catalogStaff('cashier');

    $this->get(route('admin.catalog.products.index'))->assertOk()->assertSee('زيت كريستال 1 لتر');

    Livewire::test('catalog::admin.page.products')
        ->assertDontSeeHtml('data-row-key="Enter"')
        ->assertDontSee(__('catalog::module.products.new'))
        ->call('editPrice', $this->crystal->id)
        ->assertForbidden();

    Livewire::test('catalog::admin.page.products')->call('newProduct')->assertForbidden();
    Livewire::test('catalog::admin.product-drawer')->call('open', $this->crystal->id)->assertForbidden();
});

test('a product with nothing found offers to add it by that name, on the shelf the list shows', function () {
    Livewire::test('catalog::admin.page.products')
        ->set('category', $this->oils->id)
        ->set('q', 'زيت عافية')
        ->assertSee(__('catalog::module.products.no_match', ['term' => 'زيت عافية']))
        ->call('newProduct')
        ->assertDispatched('edit-product', productId: null, name: 'زيت عافية', categoryId: $this->oils->id);

    Livewire::test('catalog::admin.product-drawer')
        ->call('open', null, 'زيت عافية', $this->oils->id)
        ->assertSet('form.name', 'زيت عافية')
        ->assertSet('form.categoryId', $this->oils->id)
        ->assertDispatched('overlay-show', id: 'product-drawer');
});

test('a new product is added with everything it is found by', function () {
    Livewire::test('catalog::admin.product-drawer')
        ->call('open')
        ->set('form.name', '  زيت عافية 1 لتر ')
        ->set('form.categoryId', $this->oils->id)
        ->set('form.unit', 'piece')
        ->set('form.price', '١٠٥٫٥٠')
        ->set('form.searchTerms', 'afia')
        ->set('form.barcode', '٦٢٢٠٠٠١')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('overlay-hide', id: 'product-drawer')
        ->assertDispatched('product-saved', notice: __('catalog::module.product.added', ['name' => 'زيت عافية 1 لتر']));

    $product = Product::query()->where('name', 'زيت عافية 1 لتر')->firstOrFail();

    expect($product->category_id)->toBe($this->oils->id)
        ->and($product->unit)->toBe(SaleUnit::Piece)
        ->and($product->price->toDecimalString())->toBe('105.50')
        ->and($product->barcode)->toBe('6220001')
        ->and($product->sku)->toBeNull()
        ->and($product->slug)->not->toBe('')
        ->and($product->search_name)->toContain('afia');
});

test('the drawer refuses what would confuse the counter', function (array $typed, string $field) {
    Livewire::test('catalog::admin.product-drawer')
        ->call('open')
        ->set(['form.name' => 'منتج جديد', 'form.categoryId' => $this->oils->id, 'form.price' => '10', ...$typed])
        ->call('save')
        ->assertHasErrors($field);

    expect(Product::query()->count())->toBe(3);
})->with([
    'a name already used' => [['form.name' => 'زيت سائب'], 'form.name'],
    'no name' => [['form.name' => '  '], 'form.name'],
    'no shelf' => [['form.categoryId' => null], 'form.categoryId'],
    'no price' => [['form.price' => ''], 'form.price'],
    'a price of nothing' => [['form.price' => '0'], 'form.price'],
    'a unit that is not one' => [['form.unit' => 'box'], 'form.unit'],
    'a barcode already used' => [['form.barcode' => '6221234500017'], 'form.barcode'],
    'a barcode with spaces' => [['form.barcode' => '622 123'], 'form.barcode'],
]);

test('a name an archived product has points to the archive', function () {
    $this->loose->update(['is_active' => false]);

    Livewire::test('catalog::admin.product-drawer')
        ->call('open')
        ->set(['form.name' => 'زيت سائب', 'form.categoryId' => $this->oils->id, 'form.price' => '10'])
        ->call('save')
        ->assertHasErrors(['form.name' => __('catalog::module.product.name_taken_archived')]);
});

test('an archived shelf is not one a product can be put on', function () {
    $this->rice->update(['is_active' => false]);

    $drawer = Livewire::test('catalog::admin.product-drawer')->call('open', $this->crystal->id);

    expect($drawer->instance()->shelves)->toBe([$this->oils->id => 'زيوت وسمنة']);

    $drawer->set('form.categoryId', $this->rice->id)->call('save')->assertHasErrors('form.categoryId');
});

test('a product is changed from the drawer, keeping its slug', function () {
    $slug = $this->crystal->slug;

    Livewire::test('catalog::admin.product-drawer')
        ->call('open', $this->crystal->id)
        ->assertSet('form.name', 'زيت كريستال 1 لتر')
        ->assertSet('form.price', '90.00')
        ->assertSet('form.barcode', '6221234500017')
        ->set('form.name', 'زيت كريستال 800 مل')
        ->set('form.price', '80')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('product-saved', notice: __('catalog::module.product.saved', ['name' => 'زيت كريستال 800 مل']));

    expect($this->crystal->refresh()->name)->toBe('زيت كريستال 800 مل')
        ->and($this->crystal->price->toDecimalString())->toBe('80.00')
        ->and($this->crystal->slug)->toBe($slug);
});

test('a product is archived and put back on sale', function () {
    Livewire::test('catalog::admin.product-drawer')
        ->call('open', $this->crystal->id)
        ->call('archive')
        ->assertDispatched('product-saved', notice: __('catalog::module.product.archived_notice', ['name' => 'زيت كريستال 1 لتر']));

    expect($this->crystal->refresh()->is_active)->toBeFalse();

    Livewire::test('catalog::admin.product-drawer')
        ->call('open', $this->crystal->id)
        ->assertSet('archived', true)
        ->assertSee(__('catalog::module.product.is_archived'))
        ->call('restore')
        ->assertHasNoErrors();

    expect($this->crystal->refresh()->is_active)->toBeTrue();
});

test('a product on an archived shelf comes back only onto one in use', function () {
    $this->crystal->update(['is_active' => false]);
    $this->loose->update(['is_active' => false]);
    $this->oils->update(['is_active' => false]);

    Livewire::test('catalog::admin.product-drawer')
        ->call('open', $this->crystal->id)
        ->call('restore')
        ->assertHasErrors(['form.categoryId' => __('catalog::module.product.restore_needs_shelf')]);

    expect($this->crystal->refresh()->is_active)->toBeFalse();
});

test('a product never sold is deleted', function () {
    Livewire::test('catalog::admin.product-drawer')
        ->call('open', $this->doha->id)
        ->call('delete')
        ->assertDispatched('product-saved', notice: __('catalog::module.product.deleted_notice', ['name' => 'أرز الضحى 1 كجم']));

    expect(Product::query()->find($this->doha->id))->toBeNull();
});

test('a product that was sold is not deleted, and the drawer offers to archive it', function () {
    $customer = app(CreateCustomer::class)->handle(['name' => 'محمد أحمد']);
    app(AccountLedgerInterface::class)->charge(new ChargeRequest($customer->id, $this->admin->getKey(), (string) Str::uuid(), [ChargeLine::product($this->doha->id, 2000)]));

    Livewire::test('catalog::admin.product-drawer')
        ->call('open', $this->doha->id)
        ->call('delete')
        ->assertSet('held', true)
        ->assertSee(__('catalog::module.product.delete_refused'))
        ->assertNotDispatched('product-saved')
        ->call('archive');

    expect($this->doha->refresh()->exists)->toBeTrue()
        ->and($this->doha->is_active)->toBeFalse();
});

test('the screen renders its products, prices, and keys in Arabic', function () {
    readingIn('ar');

    $this->get(route('admin.catalog.products.index'))
        ->assertOk()
        ->assertSee('زيت كريستال 1 لتر')
        ->assertSee('90.00')
        ->assertSee(__('catalog::module.products.new', locale: 'ar'))
        ->assertSee(__('catalog::module.products.key_price', locale: 'ar'));
});
