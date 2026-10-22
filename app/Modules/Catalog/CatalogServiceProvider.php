<?php

namespace App\Modules\Catalog;

use App\Admin\Contracts\Authorization\ProvidesPermissionsInterface;
use App\Admin\Contracts\Navigation\ProvidesAdminNavigationInterface;
use App\Admin\Contracts\Search\ProvidesAdminSearchInterface;
use App\Admin\Contracts\Trash\ProvidesTrashInterface;
use App\Admin\Navigation\NavigationItem;
use App\Admin\Navigation\NavigationSection;
use App\Foundation\Modules\ModuleServiceProvider;
use App\Modules\Catalog\Areas\Admin\Search\ProductSearch;
use App\Modules\Catalog\Areas\Admin\Trash\CategoryTrash;
use App\Modules\Catalog\Areas\Admin\Trash\ProductTrash;
use App\Modules\Catalog\Authorization\CatalogPermission;
use App\Modules\Catalog\Console\InstallStarterCatalogCommand;
use App\Modules\Catalog\Contracts\CategoryRepositoryInterface;
use App\Modules\Catalog\Contracts\ProductRepositoryInterface;
use App\Modules\Catalog\Database\Repositories\CategoryRepository;
use App\Modules\Catalog\Database\Repositories\ProductRepository;

/**
 * What the shop sells: one product per thing on the shelf, each in one
 * category, sold by the piece, the kilo, or the litre.
 *
 * Other modules read it through its repositories and copy what they sell
 * onto their own records — a sale keeps the name and price it was sold at.
 * A shop starts from `app:catalog:install-starter`, and is kept from the
 * products screen — the price changed where it stands — and the categories
 * screen.
 *
 * TODO(catalog): stock as a ledger of movements, designed first
 */
final class CatalogServiceProvider extends ModuleServiceProvider implements ProvidesAdminNavigationInterface, ProvidesAdminSearchInterface, ProvidesPermissionsInterface, ProvidesTrashInterface
{
    /** Its defaults, as config('catalog.…'). */
    protected ?string $config = 'Config/catalog.php';

    /** Its screens, by area. */
    protected array $routes = ['admin' => 'Areas/Admin/routes.php'];

    /**
     * What other modules may ask the container for. Each implementation is
     * `#[Scoped]`: one instance per request.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        ProductRepositoryInterface::class => ProductRepository::class,
        CategoryRepositoryInterface::class => CategoryRepository::class,
    ];

    public function key(): string
    {
        return 'catalog';
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([InstallStarterCatalogCommand::class]);
        }
    }

    /**
     * @return list<CatalogPermission>
     */
    public function permissions(): array
    {
        return CatalogPermission::cases();
    }

    /**
     * Products on sale, in the header's quick search.
     *
     * @return list<class-string<ProductSearch>>
     */
    public function adminSearchSources(): array
    {
        return [ProductSearch::class];
    }

    /**
     * Deleted products and categories, kept to be brought back.
     *
     * @return list<class-string<ProductTrash|CategoryTrash>>
     */
    public function adminTrashSources(): array
    {
        return [ProductTrash::class, CategoryTrash::class];
    }

    public function adminNavigation(): NavigationSection
    {
        return new NavigationSection($this->name(), [
            new NavigationItem(
                label: 'catalog::module.navigation.products',
                route: 'admin.catalog.products.index',
                icon: 'box-seam',
                permission: CatalogPermission::View,
            ),
            new NavigationItem(
                label: 'catalog::module.navigation.categories',
                route: 'admin.catalog.categories.index',
                icon: 'tags',
                permission: CatalogPermission::View,
            ),
            new NavigationItem(
                label: 'catalog::module.navigation.stock',
                route: 'admin.catalog.stock.index',
                icon: 'boxes',
                permission: CatalogPermission::AdjustStock,
            ),
        ]);
    }
}
