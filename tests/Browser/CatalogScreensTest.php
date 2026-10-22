<?php

use App\Foundation\Area\Area;
use App\Modules\Catalog\Contracts\Enums\SaleUnit;
use App\Modules\Catalog\Database\Models\Category;
use App\Modules\Catalog\Database\Models\Product;

/*
 * The catalog screens with something on them: the screen-by-screen checks see
 * them empty, and axe does not look inside a closed drawer or dialog. Here
 * there are shelves and products, archived ones among them, the drawer and the
 * dialog are opened, and a run of price changes is worked from the keyboard.
 */

/**
 * Two shelves, one with a shelf under it, and products sold by the piece and
 * by the litre.
 *
 * @return array{crystal: Product, loose: Product}
 */
function stockedShelves(): array
{
    actingAsAdmin();

    $oils = Category::factory()->create(['name' => 'زيوت وسمنة', 'icon' => 'droplet', 'sort_order' => 10]);
    $rice = Category::factory()->create(['name' => 'أرز ومكرونة', 'icon' => 'basket', 'sort_order' => 20]);
    $looseOils = Category::factory()->create(['name' => 'زيوت فرط', 'parent_id' => $oils->id]);
    Category::factory()->inactive()->create(['name' => 'موسمي', 'sort_order' => 30]);

    $crystal = Product::factory()->for($oils)->priced('90.00')->create(['name' => 'زيت كريستال 1 لتر', 'search_terms' => 'crystal', 'barcode' => '6221234500017']);
    $loose = Product::factory()->for($looseOils)->priced('75.00')->create(['name' => 'زيت سائب', 'unit' => SaleUnit::Litre]);
    Product::factory()->for($rice)->priced('38.00')->create(['name' => 'أرز الضحى 1 كجم']);
    Product::factory()->for($rice)->inactive()->create(['name' => 'أرز قديم']);

    return ['crystal' => $crystal, 'loose' => $loose];
}

function catalogPage(string $route, string $language, string $mode): mixed
{
    test()->withCookie(Area::Admin->localeCookie(), $language);

    $page = visit(route($route, absolute: false));

    return $mode === 'dark' ? $page->inDarkMode() : $page->inLightMode();
}

test('the products screen, a price open for typing, passes axe', function (string $language, string $mode) {
    stockedShelves();

    catalogPage('admin.catalog.products.index', $language, $mode)
        ->assertSee('زيت كريستال 1 لتر')
        ->click('#product-rows [data-row-key="Enter"] >> nth=0')
        ->assertVisible('#price-edit')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
})->with(['ar', 'en'])->with(['light', 'dark']);

test('the product drawer, open on a product, passes axe', function (string $language, string $mode) {
    stockedShelves();

    catalogPage('admin.catalog.products.index', $language, $mode)
        ->click('#product-rows [data-row] .btn-outline-secondary:not(.price-button) >> nth=0')
        ->assertVisible('#product-drawer.show')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
})->with(['ar', 'en'])->with(['light', 'dark']);

test('the categories screen and its dialog, open, pass axe', function (string $language, string $mode) {
    stockedShelves();

    catalogPage('admin.catalog.categories.index', $language, $mode)
        ->assertSee('زيوت فرط')
        ->assertNoAccessibilityIssues()
        ->keys('.app-content', 'n')
        ->assertVisible('#category-form.show')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
})->with(['ar', 'en'])->with(['light', 'dark']);

test('prices changed one after another from the keyboard: /, a name, ↓, Enter, a price, Enter, ↓, Enter, Esc', function () {
    ['crystal' => $crystal, 'loose' => $loose] = stockedShelves();

    $page = catalogPage('admin.catalog.products.index', 'ar', 'light')
        ->keys('.app-content', '/')
        ->assertScript('document.activeElement.id', 'product-search')
        ->type('#product-search', 'زيت')
        ->assertDontSee('أرز الضحى 1 كجم')
        ->keys('#product-search', 'ArrowDown')
        ->assertScript('document.activeElement.id', "product-row-{$crystal->id}")
        ->keys("#product-row-{$crystal->id}", 'Enter')
        ->assertScript('document.activeElement.id', 'price-edit')
        ->assertScript('document.activeElement.selectionStart === 0 && document.activeElement.selectionEnd === document.activeElement.value.length')
        ->keys('#price-edit', ['9', '5', 'Enter'])
        ->assertSee(__('catalog::module.products.price_changed', ['name' => 'زيت كريستال 1 لتر', 'price' => '95.00', 'before' => '90.00'], 'ar'))
        ->assertScript('document.activeElement.id', "product-row-{$crystal->id}")
        ->keys("#product-row-{$crystal->id}", 'ArrowDown')
        ->assertScript('document.activeElement.id', "product-row-{$loose->id}")
        ->keys("#product-row-{$loose->id}", 'Enter')
        ->assertScript('document.activeElement.id', 'price-edit')
        ->keys('#price-edit', ['1', 'Escape'])
        ->assertMissing('#price-edit')
        ->assertScript('document.activeElement.id', "product-row-{$loose->id}")
        ->assertNoJavaScriptErrors();

    expect($crystal->refresh()->price->toDecimalString())->toBe('95.00')
        ->and($loose->refresh()->price->toDecimalString())->toBe('75.00');
});

test('both catalog screens, and the drawer, fit a 375px phone', function () {
    stockedShelves();

    $page = catalogPage('admin.catalog.products.index', 'ar', 'light');

    expect(phoneOverflow($page))->toBeLessThanOrEqual(0);

    $page->click('#product-rows [data-row-key="Enter"] >> nth=0')->assertVisible('#price-edit');

    expect(phoneOverflow($page))->toBeLessThanOrEqual(0);

    $page->keys('#price-edit', 'Escape')
        ->click('#product-rows [data-row] .btn-outline-secondary:not(.price-button) >> nth=0')
        ->assertVisible('#product-drawer.show');

    expect(phoneOverflow($page))->toBeLessThanOrEqual(0);

    expect(phoneOverflow(catalogPage('admin.catalog.categories.index', 'ar', 'light')))->toBeLessThanOrEqual(0);
});
