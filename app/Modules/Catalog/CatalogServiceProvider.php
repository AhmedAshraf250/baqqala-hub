<?php

namespace App\Modules\Catalog;

use App\Admin\Contracts\Authorization\ProvidesPermissionsInterface;
use App\Admin\Contracts\Navigation\ProvidesAdminNavigationInterface;
use App\Admin\Navigation\NavigationItem;
use App\Admin\Navigation\NavigationSection;
use App\Foundation\Modules\ModuleServiceProvider;
use App\Modules\Catalog\Authorization\CatalogPermission;

/**
 * What the shop sells, and how much of it there is.
 *
 * TODO(catalog): product CRUD, category tree, stock adjustments with a reason
 * TODO(catalog): barcode lookup, and low-stock reporting
 */
final class CatalogServiceProvider extends ModuleServiceProvider implements ProvidesAdminNavigationInterface, ProvidesPermissionsInterface
{
    /** Its defaults, as config('catalog.…'). */
    protected ?string $config = 'Config/catalog.php';

    /** Its screens, by area. */
    protected array $routes = ['admin' => 'Areas/Admin/routes.php'];

    public function key(): string
    {
        return 'catalog';
    }

    /**
     * @return list<CatalogPermission>
     */
    public function permissions(): array
    {
        return CatalogPermission::cases();
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
