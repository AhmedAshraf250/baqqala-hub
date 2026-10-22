<?php

use App\Foundation\Area\Area;
use App\Modules\Catalog\Database\Models\Category;
use App\Modules\Catalog\Database\Models\Product;
use App\Modules\Customers\Domain\Actions\CreateCustomer;

/*
 * How the admin's controls behave in a real browser: the question asked
 * before anything is archived or deleted, the select that searches, the
 * fields that take only figures, and the drawer's backdrop surviving a
 * redraw.
 */

/**
 * Two shelves, one product on each.
 *
 * @return array{oils: Category, dairy: Category, product: Product}
 */
function twoShelves(): array
{
    actingAsAdmin();
    test()->withCookie(Area::Admin->localeCookie(), 'ar');

    $oils = Category::factory()->create(['name' => 'زيوت وسمنة', 'sort_order' => 10]);
    $dairy = Category::factory()->create(['name' => 'ألبان وبيض', 'sort_order' => 20]);
    $empty = Category::factory()->create(['name' => 'موسمي', 'sort_order' => 30]);
    $product = Product::factory()->for($oils)->priced('90.00')->create(['name' => 'زيت كريستال 1 لتر']);
    Product::factory()->for($dairy)->create(['name' => 'لبن المراعي 1 لتر']);

    return ['oils' => $oils, 'dairy' => $dairy, 'empty' => $empty, 'product' => $product];
}

test('archiving asks first: «no» leaves it, «yes» archives it, and the question passes axe', function () {
    ['empty' => $empty] = twoShelves();

    $page = visit(route('admin.catalog.categories.index', absolute: false))->inLightMode();

    $page->click("#category-row-{$empty->id} [wire\\:click^=\"archive\"]")
        ->assertVisible('#confirm-dialog.show')
        ->assertSee(__('catalog::module.categories.archive_confirm', ['name' => 'موسمي'], 'ar'));

    $page->script('() => new Promise((resolve) => setTimeout(resolve, 400))');
    $page->assertNoAccessibilityIssues()
        ->click('#confirm-dialog [data-confirm-cancel]')
        ->assertMissing('#confirm-dialog.show');

    expect($empty->refresh()->is_active)->toBeTrue();

    $page->click("#category-row-{$empty->id} [wire\\:click^=\"archive\"]")
        ->assertVisible('#confirm-dialog.show')
        ->click('#confirm-dialog [data-confirm-ok]')
        ->assertSee(__('catalog::module.categories.archived_notice', ['name' => 'موسمي'], 'ar'))
        ->assertNoJavaScriptErrors();

    expect($empty->refresh()->is_active)->toBeFalse();
});

test('a category is found by typing in its select, and saved', function () {
    ['dairy' => $dairy, 'product' => $product] = twoShelves();

    visit(route('admin.catalog.products.index', absolute: false))
        ->inLightMode()
        ->click("#product-row-{$product->id} .btn-outline-secondary:not(.price-button)")
        ->assertVisible('#product-drawer.show')
        ->click('#product-category-toggle')
        ->assertVisible('#product-category-panel')
        ->assertScript('document.activeElement.matches("#product-category-panel [data-search-select-input]")')
        ->type('#product-category-panel [data-search-select-input]', 'البان')
        ->assertScript('[...document.querySelectorAll("#product-category-listbox [role=option]")].filter((option) => ! option.hidden).length', 1)
        ->keys('#product-category-panel [data-search-select-input]', 'Enter')
        ->assertMissing('#product-category-panel:not([hidden])')
        ->assertSeeIn('#product-category-toggle', 'ألبان وبيض')
        ->assertVisible('#product-drawer.show')
        ->click('#product-drawer [data-ctrl-enter-submit]')
        ->assertMissing('#product-drawer.show')
        ->assertNoJavaScriptErrors();

    expect($product->refresh()->category_id)->toBe($dairy->id);
});

test('a field for an amount takes only figures, Arabic digits included', function () {
    twoShelves();
    $customer = app(CreateCustomer::class)->handle(['name' => 'محمد أحمد علي']);

    visit(route('admin.accounts.show', $customer->id, absolute: false))
        ->inLightMode()
        ->click('[data-shortcut-key="-"]')
        ->assertVisible('#entry-drawer.show')
        ->typeSlowly('#entry-amount', 'شa٣5.5.0')
        ->assertValue('#entry-amount', '٣5.50');
});

test('the drawer\'s backdrop stays one, on the page body, through redraws and reopening', function () {
    twoShelves();
    $customer = app(CreateCustomer::class)->handle(['name' => 'محمد أحمد علي']);

    $backdrops = '() => [...document.querySelectorAll(".offcanvas-backdrop")].map((backdrop) => backdrop.parentElement === document.body).join()';

    $page = visit(route('admin.accounts.show', $customer->id, absolute: false))
        ->inLightMode()
        ->click('[data-shortcut-key="-"]')
        ->assertVisible('#entry-drawer.show')
        ->type('#entry-amount', '50')
        ->assertSee('50.00')
        ->assertScript($backdrops, 'true')
        ->click('#entry-drawer .btn-close')
        ->assertMissing('#entry-drawer.show')
        ->click('[data-shortcut-key="-"]')
        ->assertVisible('#entry-drawer.show')
        ->assertScript($backdrops, 'true')
        ->assertNoJavaScriptErrors();
});
