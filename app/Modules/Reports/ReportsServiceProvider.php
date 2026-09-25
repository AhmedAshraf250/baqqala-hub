<?php

namespace App\Modules\Reports;

use App\Admin\Contracts\Authorization\ProvidesPermissionsInterface;
use App\Admin\Contracts\Navigation\ProvidesAdminNavigationInterface;
use App\Admin\Navigation\NavigationItem;
use App\Admin\Navigation\NavigationSection;
use App\Foundation\Modules\ModuleServiceProvider;
use App\Modules\Reports\Authorization\ReportPermission;

/**
 * The shop's numbers.
 *
 * Deliberately depends on nothing. A reporting module that imported every other
 * module would be the one place that has to change whenever any of them does —
 * and would make every module un-removable.
 *
 * Instead, each module will contribute its own reports through a capability
 * interface, the way they contribute navigation today. That interface is not
 * written yet because there is nothing real to report; adding it when there is
 * costs one file and touches nothing here.
 *
 * TODO(reports): a ProvidesReportsInterface capability, so a module hands over
 * TODO(reports): its own figures and this module only lays them out
 */
final class ReportsServiceProvider extends ModuleServiceProvider implements ProvidesAdminNavigationInterface, ProvidesPermissionsInterface
{
    /** Its screens, by area. */
    protected array $routes = ['admin' => 'Areas/Admin/routes.php'];

    public function key(): string
    {
        return 'reports';
    }

    /**
     * @return list<ReportPermission>
     */
    public function permissions(): array
    {
        return ReportPermission::cases();
    }

    public function adminNavigation(): NavigationSection
    {
        // Shares the shell's Operations heading with its neighbours; the
        // sidebar merges them into one section.
        return new NavigationSection('shell.navigation.group.operations', [
            new NavigationItem(
                label: 'reports::module.navigation.reports',
                route: 'admin.reports.index',
                icon: 'graph-up',
                permission: ReportPermission::View,
            ),
        ]);
    }
}
