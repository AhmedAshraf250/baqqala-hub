<?php

namespace App\Modules\Suppliers;

use App\Admin\Contracts\Authorization\ProvidesPermissionsInterface;
use App\Admin\Contracts\Navigation\ProvidesAdminNavigationInterface;
use App\Admin\Navigation\NavigationItem;
use App\Admin\Navigation\NavigationSection;
use App\Foundation\Modules\ModuleServiceProvider;
use App\Modules\Suppliers\Authorization\SupplierPermission;

/**
 * The businesses the shop buys from.
 *
 * A directory, like Customers but on the other side of the counter. Kept
 * separate from Purchases so a shop that buys ad hoc can still keep a supplier
 * list, and so a product with no purchasing at all can drop that module alone.
 *
 * TODO(suppliers): the directory, contact details, and what is owed each way
 */
final class SuppliersServiceProvider extends ModuleServiceProvider implements ProvidesAdminNavigationInterface, ProvidesPermissionsInterface
{
    /** Its screens, by area. */
    protected array $routes = ['admin' => 'Areas/Admin/routes.php'];

    public function key(): string
    {
        return 'suppliers';
    }

    /**
     * @return list<SupplierPermission>
     */
    public function permissions(): array
    {
        return SupplierPermission::cases();
    }

    public function adminNavigation(): NavigationSection
    {
        return new NavigationSection($this->name(), [
            new NavigationItem(
                label: 'suppliers::module.navigation.suppliers',
                route: 'admin.suppliers.index',
                icon: 'truck',
                permission: SupplierPermission::View,
            ),
        ]);
    }
}
