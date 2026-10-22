<?php

use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\AdminUser;
use App\Modules\Catalog\Database\Models\Category;
use App\Modules\Catalog\Database\Models\Product;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    actingAsAdmin();

    $this->rice = Category::factory()->create(['name' => 'أرز ومكرونة', 'sort_order' => 10, 'icon' => 'basket']);
    $this->oils = Category::factory()->create(['name' => 'زيوت وسمنة', 'sort_order' => 20, 'icon' => 'droplet']);
    $this->dairy = Category::factory()->create(['name' => 'ألبان وبيض', 'sort_order' => 30, 'icon' => 'egg']);
});

/**
 * The shelves as the screen lists them, a sub-shelf marked by its indent.
 *
 * @return list<string>
 */
function listedShelves(mixed $screen): array
{
    return array_map(
        static fn (array $row): string => str_repeat('— ', $row['depth']).$row['category']->name,
        $screen->instance()->shelves,
    );
}

test('the shelves are listed in their order, each sub-shelf after the one above it, with what is on them', function () {
    $loose = Category::factory()->create(['name' => 'زيوت فرط', 'parent_id' => $this->oils->id]);
    Product::factory()->for($this->oils)->count(2)->create();
    Product::factory()->for($this->oils)->inactive()->create();

    $screen = Livewire::test('catalog::admin.page.categories');

    expect(listedShelves($screen))->toBe(['أرز ومكرونة', 'زيوت وسمنة', '— زيوت فرط', 'ألبان وبيض']);

    $oils = $screen->instance()->shelves[1];

    expect($oils['category']->on_sale_count)->toBe(2)
        ->and($oils['category']->products_count)->toBe(3)
        ->and($oils['holdsShelves'])->toBeTrue()
        ->and($screen->instance()->shelves[2]['category']->is($loose))->toBeTrue();
});

test('a new shelf goes last among the ones beside it', function () {
    Livewire::test('catalog::admin.category-form')
        ->call('open')
        ->set('name', 'منظفات')
        ->set('icon', 'bucket')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('category-saved', notice: __('catalog::module.category.added', ['name' => 'منظفات']));

    Livewire::test('catalog::admin.category-form')
        ->call('open')
        ->set('name', 'زيوت فرط')
        ->set('parentId', $this->oils->id)
        ->call('save')
        ->assertHasNoErrors();

    $cleaning = Category::query()->where('name', 'منظفات')->firstOrFail();

    expect($cleaning->sort_order)->toBe(40)
        ->and($cleaning->icon)->toBe('bucket')
        ->and($cleaning->slug)->not->toBe('')
        ->and(Category::query()->where('name', 'زيوت فرط')->value('parent_id'))->toBe($this->oils->id);

    expect(listedShelves(Livewire::test('catalog::admin.page.categories')))->toBe(['أرز ومكرونة', 'زيوت وسمنة', '— زيوت فرط', 'ألبان وبيض', 'منظفات']);
});

test('two levels at most', function () {
    $loose = Category::factory()->create(['name' => 'زيوت فرط', 'parent_id' => $this->oils->id]);

    // Under a sub-shelf: a third level.
    Livewire::test('catalog::admin.category-form')
        ->call('open')
        ->set(['name' => 'زيت ذرة', 'parentId' => $loose->id])
        ->call('save')
        ->assertHasErrors('parentId');

    // A shelf holding shelves stays at the top.
    Livewire::test('catalog::admin.category-form')
        ->call('open', $this->oils->id)
        ->assertSet('holdsShelves', true)
        ->set('parentId', $this->rice->id)
        ->call('save')
        ->assertHasErrors(['parentId' => __('catalog::module.category.holds_shelves')]);

    // Not under itself.
    Livewire::test('catalog::admin.category-form')
        ->call('open', $this->rice->id)
        ->set('parentId', $this->rice->id)
        ->call('save')
        ->assertHasErrors('parentId');

    expect($this->oils->refresh()->parent_id)->toBeNull()
        ->and($this->rice->refresh()->parent_id)->toBeNull();
});

test('a shelf is renamed and given another icon, keeping its place', function () {
    Livewire::test('catalog::admin.category-form')
        ->call('open', $this->oils->id)
        ->assertSet('name', 'زيوت وسمنة')
        ->assertSet('icon', 'droplet')
        ->set('name', 'زيوت')
        ->set('icon', 'fire')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->oils->refresh()->name)->toBe('زيوت')
        ->and($this->oils->icon)->toBe('fire')
        ->and($this->oils->sort_order)->toBe(20);
});

test('a shelf name is used once, and its icon is one of the offered', function (array $typed, string $field) {
    Livewire::test('catalog::admin.category-form')
        ->call('open')
        ->set(['name' => 'منظفات', ...$typed])
        ->call('save')
        ->assertHasErrors($field);
})->with([
    'a name already used' => [['name' => 'زيوت وسمنة'], 'name'],
    'no name' => [['name' => ' '], 'name'],
    'an icon not offered' => [['icon' => 'skull'], 'icon'],
]);

test('a shelf moves up and down among the ones beside it', function () {
    $screen = Livewire::test('catalog::admin.page.categories')->call('move', $this->dairy->id, -1);

    expect(listedShelves($screen))->toBe(['أرز ومكرونة', 'ألبان وبيض', 'زيوت وسمنة']);

    $screen->call('move', $this->rice->id, 1);

    expect(listedShelves($screen))->toBe(['ألبان وبيض', 'أرز ومكرونة', 'زيوت وسمنة'])
        ->and(Category::query()->inOrder()->pluck('sort_order')->all())->toBe([10, 20, 30]);

    // Past the end, nothing moves.
    $screen->call('move', $this->oils->id, 1);

    expect(listedShelves($screen))->toBe(['ألبان وبيض', 'أرز ومكرونة', 'زيوت وسمنة']);
});

test('a shelf with products on sale is not archived; one holding only archived products is', function () {
    $product = Product::factory()->for($this->oils)->create();

    Livewire::test('catalog::admin.page.categories')
        ->call('archive', $this->oils->id)
        ->assertSet('problem', __('catalog::module.categories.archive_refused', ['name' => 'زيوت وسمنة']));

    expect($this->oils->refresh()->is_active)->toBeTrue();

    $product->update(['is_active' => false]);

    Livewire::test('catalog::admin.page.categories')
        ->call('archive', $this->oils->id)
        ->assertSet('problem', null)
        ->assertSet('notice', __('catalog::module.categories.archived_notice', ['name' => 'زيوت وسمنة']));

    expect($this->oils->refresh()->is_active)->toBeFalse();
});

test('a sub-shelf comes back only under a shelf in use', function () {
    $loose = Category::factory()->inactive()->create(['name' => 'زيوت فرط', 'parent_id' => $this->oils->id]);
    $this->oils->update(['is_active' => false]);

    Livewire::test('catalog::admin.page.categories')
        ->call('restore', $loose->id)
        ->assertSet('problem', __('catalog::module.categories.restore_refused', ['name' => 'زيوت فرط']));

    Livewire::test('catalog::admin.page.categories')->call('restore', $this->oils->id)->assertSet('problem', null);
    Livewire::test('catalog::admin.page.categories')->call('restore', $loose->id)->assertSet('problem', null);

    expect($loose->refresh()->is_active)->toBeTrue();
});

test('an empty shelf is deleted; one holding anything is not', function () {
    Product::factory()->for($this->oils)->inactive()->create();
    Category::factory()->create(['parent_id' => $this->rice->id]);

    Livewire::test('catalog::admin.page.categories')
        ->call('delete', $this->oils->id)
        ->assertSet('problem', __('catalog::module.categories.delete_refused', ['name' => 'زيوت وسمنة']))
        ->call('delete', $this->rice->id)
        ->assertSet('problem', __('catalog::module.categories.delete_refused', ['name' => 'أرز ومكرونة']))
        ->call('delete', $this->dairy->id)
        ->assertSet('notice', __('catalog::module.categories.deleted_notice', ['name' => 'ألبان وبيض']));

    expect(Category::query()->pluck('name')->all())->toContain('زيوت وسمنة', 'أرز ومكرونة')
        ->and(Category::query()->whereKey($this->dairy->id)->exists())->toBeFalse();
});

test('a cashier sees the shelves and changes none of them', function () {
    $cashier = AdminUser::factory()->create();
    $cashier->syncRoles('cashier');
    $this->actingAs($cashier, Area::Admin->guard());

    $this->get(route('admin.catalog.categories.index'))->assertOk()->assertSee('زيوت وسمنة');

    Livewire::test('catalog::admin.page.categories')
        ->assertDontSee(__('catalog::module.categories.new'))
        ->call('move', $this->oils->id, -1)
        ->assertForbidden();

    Livewire::test('catalog::admin.page.categories')->call('archive', $this->oils->id)->assertForbidden();
    Livewire::test('catalog::admin.category-form')->call('open', $this->oils->id)->assertForbidden();

    expect($this->oils->refresh()->sort_order)->toBe(20);
});

test('a shelf links to its products', function () {
    $this->get(route('admin.catalog.categories.index'))
        ->assertOk()
        ->assertSee(route('admin.catalog.products.index', ['category' => $this->oils->id]), escape: false);
});
