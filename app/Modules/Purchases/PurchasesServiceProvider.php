<?php

namespace App\Modules\Purchases;

use App\Admin\Contracts\Authorization\ProvidesPermissionsInterface;
use App\Admin\Contracts\Navigation\ProvidesAdminNavigationInterface;
use App\Admin\Navigation\NavigationItem;
use App\Admin\Navigation\NavigationSection;
use App\Foundation\Contracts\Modules\DependsOnModulesInterface;
use App\Foundation\Modules\ModuleServiceProvider;
use App\Modules\Purchases\Authorization\PurchasePermission;

/**
 * Goods coming into the shop.
 *
 * TODO(purchases): purchase invoices, goods received, and the stock they add
 */
final class PurchasesServiceProvider extends ModuleServiceProvider implements DependsOnModulesInterface, ProvidesAdminNavigationInterface, ProvidesPermissionsInterface
{
    /** Its screens, by area. */
    protected array $routes = ['admin' => 'Areas/Admin/routes.php'];

    public function key(): string
    {
        return 'purchases';
    }

    /**
     * Bought from a supplier, and it is a product that arrives.
     *
     * @return list<string>
     */
    public function dependsOn(): array
    {
        return ['suppliers', 'catalog'];
    }

    /**
     * @return list<PurchasePermission>
     */
    public function permissions(): array
    {
        return PurchasePermission::cases();
    }

    public function adminNavigation(): NavigationSection
    {
        // Shares the shell's Operations heading with its neighbours; the
        // sidebar merges them into one section.
        return new NavigationSection('shell.navigation.group.operations', [
            new NavigationItem(
                label: 'purchases::module.navigation.purchases',
                route: 'admin.purchases.index',
                icon: 'cart-plus',
                permission: PurchasePermission::View,
            ),
        ]);
    }
}
