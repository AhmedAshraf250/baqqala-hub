<?php

namespace App\Modules\Sales;

use App\Admin\Contracts\Authorization\ProvidesPermissionsInterface;
use App\Admin\Contracts\Navigation\ProvidesAdminNavigationInterface;
use App\Admin\Navigation\NavigationItem;
use App\Admin\Navigation\NavigationSection;
use App\Foundation\Contracts\Modules\DependsOnModulesInterface;
use App\Foundation\Modules\ModuleServiceProvider;
use App\Modules\Sales\Authorization\SalePermission;

/**
 * Goods leaving the shop.
 *
 * A sale that is paid for ends here. A sale taken on account also posts an
 * entry to the customer's ledger — which this module will do by announcing it,
 * not by calling the Accounts module, so neither has to know the other's shape.
 *
 * TODO(sales): the counter screen, receipts, and the daily takings
 * TODO(sales): announce a sale so the Accounts module can post it on account
 */
final class SalesServiceProvider extends ModuleServiceProvider implements DependsOnModulesInterface, ProvidesAdminNavigationInterface, ProvidesPermissionsInterface
{
    /** Its screens, by area. */
    protected array $routes = ['admin' => 'Areas/Admin/routes.php'];

    public function key(): string
    {
        return 'sales';
    }

    /**
     * Sold to someone, and it is a product that is sold.
     *
     * @return list<string>
     */
    public function dependsOn(): array
    {
        return ['customers', 'catalog'];
    }

    /**
     * @return list<SalePermission>
     */
    public function permissions(): array
    {
        return SalePermission::cases();
    }

    public function adminNavigation(): NavigationSection
    {
        // Shares the shell's Operations heading with its neighbours; the
        // sidebar merges them into one section.
        return new NavigationSection('shell.navigation.group.operations', [
            new NavigationItem(
                label: 'sales::module.navigation.sales',
                route: 'admin.sales.index',
                icon: 'receipt',
                permission: SalePermission::View,
            ),
        ]);
    }
}
