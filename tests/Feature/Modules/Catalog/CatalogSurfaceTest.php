<?php

use App\Foundation\Money\Money;
use App\Modules\Catalog\Contracts\CategoryRepositoryInterface;
use App\Modules\Catalog\Contracts\Data\ProductData;
use App\Modules\Catalog\Contracts\Enums\SaleUnit;
use App\Modules\Catalog\Contracts\ProductRepositoryInterface;
use App\Modules\Catalog\Database\Models\Category;
use App\Modules\Catalog\Database\Models\Product;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(LazilyRefreshDatabase::class);

test('a product crosses the boundary as data, with its unit and price per unit', function () {
    $product = Product::factory()->byWeight()->priced('140.00')->create(['name' => 'جبنة بيضا سايبة']);

    $data = app(ProductRepositoryInterface::class)->find($product->getKey());

    expect($data)->toBeInstanceOf(ProductData::class)
        ->and($data->unit)->toBe(SaleUnit::Kilogram)
        ->and($data->unit->allowsFractions())->toBeTrue()
        ->and($data->price->equals(Money::fromDecimalString('140.00')))->toBeTrue();
});

test('saving a product folds its name and other names for a search', function () {
    $product = Product::factory()->create(['name' => 'حفاضات بامبرز مقاس 4', 'search_terms' => 'Pampers بمبرز']);

    expect($product->search_name)->toBe('حفاضات بامبرز مقاس 4 pampers بمبرز');

    // Rewritten whenever either changes, never left behind.
    $product->update(['search_terms' => null]);

    expect($product->fresh()->search_name)->toBe('حفاضات بامبرز مقاس 4');
});

test('the products of a sale are read in one query, archived ones included', function () {
    $products = Product::factory()->count(3)->create();
    $products[2]->update(['is_active' => false]);

    DB::enableQueryLog();
    $found = app(ProductRepositoryInterface::class)->findMany($products->modelKeys());

    expect(DB::getQueryLog())->toHaveCount(1)
        ->and($found->keys()->all())->toEqualCanonicalizing($products->modelKeys())
        ->and($found[$products[2]->getKey()]->isActive)->toBeFalse();
});

test('what may be sold today is in the shelves\' order, then by name', function () {
    $drinks = Category::factory()->create(['name' => 'مشروبات', 'sort_order' => 20]);
    $dairy = Category::factory()->create(['name' => 'ألبان', 'sort_order' => 10]);

    Product::factory()->for($drinks)->create(['name' => 'بيبسي']);
    Product::factory()->for($dairy)->create(['name' => 'زبادي']);
    Product::factory()->for($dairy)->create(['name' => 'جبنة']);
    Product::factory()->for($dairy)->create(['name' => 'لبن قديم', 'is_active' => false]);

    expect(app(ProductRepositoryInterface::class)->getActive()->pluck('name')->all())
        ->toBe(['جبنة', 'زبادي', 'بيبسي']);
});

test('the catalog version changes when a price does, and only then', function () {
    $product = Product::factory()->create();
    $products = app(ProductRepositoryInterface::class);

    $before = $products->getCatalogVersion();

    expect($products->getCatalogVersion())->toBe($before);

    $this->travel(1)->minute();
    $product->update(['price' => Money::fromDecimalString('99.00')]);

    expect($products->getCatalogVersion())->not->toBe($before);
});

test('the catalog version changes for every edit, even two within one second', function (Closure $edit) {
    $this->freezeSecond();
    $product = Product::factory()->priced('10.00')->create(['name' => 'Rice']);
    $products = app(ProductRepositoryInterface::class);
    $before = $products->getCatalogVersion();

    $edit($product);

    expect($products->getCatalogVersion())->not->toBe($before);
})->with([
    'a price' => [fn (Product $product) => $product->update(['price' => Money::fromDecimalString('11.00')])],
    'a name' => [fn (Product $product) => $product->update(['name' => 'Rice, 5 kg'])],
    'an archiving' => [fn (Product $product) => $product->update(['is_active' => false])],
    'a shelf' => [fn (Product $product) => $product->update(['category_id' => Category::factory()->create()->id])],
    'a category renamed' => [fn (Product $product) => $product->category->update(['name' => $product->category->name.' and more'])],
]);

test('categories come in the owner\'s order, archived ones only when asked', function () {
    Category::factory()->create(['name' => 'ب', 'sort_order' => 20]);
    Category::factory()->create(['name' => 'أ', 'sort_order' => 10, 'icon' => 'basket']);
    Category::factory()->inactive()->create(['name' => 'ج', 'sort_order' => 5]);

    $categories = app(CategoryRepositoryInterface::class);

    expect($categories->getAll()->pluck('name')->all())->toBe(['أ', 'ب'])
        ->and($categories->getAll()->first()->icon)->toBe('basket')
        ->and($categories->getAll(includeArchived: true)->pluck('name')->all())->toBe(['ج', 'أ', 'ب']);
});
