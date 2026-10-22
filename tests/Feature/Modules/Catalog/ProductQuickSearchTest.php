<?php

use App\Modules\Catalog\Contracts\Enums\SaleUnit;
use App\Modules\Catalog\Database\Models\Category;
use App\Modules\Catalog\Database\Models\Product;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

test('a product on sale is found the way the picker finds it, with its shelf and its price', function () {
    actingAsAdmin();

    $oils = Category::factory()->create(['name' => 'زيوت وسمنة']);
    Product::factory()->for($oils)->priced('75.00')->create(['name' => 'زيت سائب', 'unit' => SaleUnit::Litre, 'search_terms' => 'زيت فرط']);
    Product::factory()->for($oils)->inactive()->create(['name' => 'زيت قديم']);

    $palette = Livewire::test('admin::layout.search-palette')->call('open')->set('q', 'زيت');
    $found = collect($palette->instance()->groups)->firstWhere('label', __('catalog::module.search.products'))['results'];

    expect(array_map(fn ($result) => [$result->title, $result->subtitle, $result->badge, $result->url], $found))->toBe([[
        'زيت سائب',
        'زيوت وسمنة',
        __('catalog::module.search.price', ['price' => '75.00', 'unit' => SaleUnit::Litre->label()]),
        route('admin.catalog.products.index', ['q' => 'زيت سائب']),
    ]]);
});
