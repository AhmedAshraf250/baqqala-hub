<?php

use App\Foundation\Money\Money;
use App\Modules\Catalog\Contracts\Enums\SaleUnit;
use App\Modules\Catalog\Database\Models\Category;
use App\Modules\Catalog\Database\Models\Product;
use App\Modules\Catalog\Domain\Actions\InstallStarterCatalog;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);

/**
 * The file as written, before anything reads it into the database.
 *
 * @return array<string, array{name: string, icon?: string, products: list<array<string, string>>}>
 */
function starterCatalog(): array
{
    return require app(InstallStarterCatalog::class)->file();
}

test('the starter catalog is twenty shelves and 175 products', function () {
    $catalog = starterCatalog();

    expect($catalog)->toHaveCount(20)
        ->and(array_sum(array_map(fn (array $shelf): int => count($shelf['products']), $catalog)))->toBe(175);
});

test('every line of the file is something the catalog can store', function () {
    $keys = [];
    $names = [];

    foreach (starterCatalog() as $categoryKey => $shelf) {
        expect($categoryKey)->toBe(Str::slug($categoryKey))
            ->and($shelf['name'])->not->toBe('')
            ->and(is_file(base_path("node_modules/bootstrap-icons/icons/{$shelf['icon']}.svg")))->toBeTrue("[{$shelf['icon']}] is not a Bootstrap icon.");

        foreach ($shelf['products'] as $product) {
            expect($product['key'])->toBe(Str::slug($product['key']))
                ->and(SaleUnit::tryFrom($product['unit']))->not->toBeNull("[{$product['key']}] has an unknown unit.")
                ->and(Money::fromDecimalString($product['price'])->isPositive())->toBeTrue();

            $keys[] = $product['key'];
            $names[] = $product['name'];
        }
    }

    // A key is a slug, and two products answering to one name would make a
    // search guess.
    expect($keys)->toHaveCount(count(array_unique($keys)))
        ->and($names)->toHaveCount(count(array_unique($names)));
});

test('installing it adds every shelf and product, in the file\'s order', function () {
    $result = app(InstallStarterCatalog::class)->handle();

    expect($result)->toBe(['categories' => 20, 'products' => 175, 'unchanged' => 0])
        ->and(Category::query()->inOrder()->pluck('slug')->first())->toBe('rice-pasta')
        ->and(Category::query()->inOrder()->pluck('slug')->last())->toBe('misc');

    $oil = Product::query()->where('slug', 'oil-loose')->firstOrFail();

    expect($oil->unit)->toBe(SaleUnit::Litre)
        ->and($oil->price->toDecimalString())->toBe('75.00')
        ->and($oil->category->slug)->toBe('oils-ghee')
        // Folded: «سائب» is searched as «سايب», the way it is often typed.
        ->and($oil->search_name)->toBe('زيت سايب زيت فرط');
});

test('installing it again adds nothing and changes nothing the owner changed', function () {
    $install = app(InstallStarterCatalog::class);
    $install->handle();

    // What an owner does in the first week: a price corrected, a name
    // changed, a product they do not sell archived, a shelf renamed.
    Product::query()->where('slug', 'rice-doha-5kg')->firstOrFail()->update(['price' => Money::fromDecimalString('165.00')]);
    Product::query()->where('slug', 'chipsy-small')->firstOrFail()->update(['name' => 'شيبسي 5 جنيه']);
    Product::query()->where('slug', 'red-bull')->firstOrFail()->update(['is_active' => false]);
    Category::query()->where('slug', 'feed')->firstOrFail()->update(['name' => 'العليقة']);

    expect($install->handle())->toBe(['categories' => 0, 'products' => 0, 'unchanged' => 175])
        ->and(Product::query()->where('slug', 'rice-doha-5kg')->value('price')->toDecimalString())->toBe('165.00')
        ->and(Product::query()->where('slug', 'chipsy-small')->value('name'))->toBe('شيبسي 5 جنيه')
        ->and(Product::query()->where('slug', 'red-bull')->value('is_active'))->toBeFalse()
        ->and(Category::query()->where('slug', 'feed')->value('name'))->toBe('العليقة');
});

test('a product in the trash stays there; one deleted for good comes back, on the shelf it belongs to', function () {
    $install = app(InstallStarterCatalog::class);
    $install->handle();

    // Deleted on purpose: installing again does not bring it back.
    Product::query()->where('slug', 'matches')->delete();

    expect($install->handle())->toBe(['categories' => 0, 'products' => 0, 'unchanged' => 175])
        ->and(Product::query()->where('slug', 'matches')->exists())->toBeFalse();

    // Gone for good, it is missing again — and installing adds it.
    Product::withTrashed()->where('slug', 'matches')->forceDelete();

    expect($install->handle())->toBe(['categories' => 0, 'products' => 1, 'unchanged' => 174])
        ->and(Product::query()->where('slug', 'matches')->firstOrFail()->category->slug)->toBe('misc');
});

test('the command installs it and says the prices are samples', function () {
    Artisan::call('app:catalog:install-starter');

    expect(Artisan::output())
        ->toContain('Added 20 categories and 175 products')
        ->toContain('The prices are samples')
        ->and(Product::query()->count())->toBe(175);
});
