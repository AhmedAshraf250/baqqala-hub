<?php

namespace App\Modules\Reports;

use App\Admin\Contracts\Authorization\ProvidesPermissionsInterface;
use App\Admin\Contracts\Navigation\ProvidesAdminNavigationInterface;
use App\Admin\Navigation\NavigationItem;
use App\Admin\Navigation\NavigationSection;
use App\Foundation\Contracts\Modules\DependsOnModulesInterface;
use App\Foundation\Modules\ModuleServiceProvider;
use App\Modules\Reports\Authorization\ReportPermission;

/**
 * The shop's numbers, for the owner: what is owed now and by whom, who has
 * gone longest without paying, what was taken and paid day by day, each
 * person's share, what was put right, and what was taken most.
 *
 * The one module that reads other modules' tables directly — agreed, because
 * a report sums across them in one query — and it only reads: it writes
 * nothing, anywhere, and links to their screens by name. It declares what it
 * reads, so it cannot run without them. Every total leaves out voided entries
 * and the reversals that voided them.
 */
final class ReportsServiceProvider extends ModuleServiceProvider implements DependsOnModulesInterface, ProvidesAdminNavigationInterface, ProvidesPermissionsInterface
{
    /** Its screens, by area. */
    protected array $routes = ['admin' => 'Areas/Admin/routes.php'];

    public function key(): string
    {
        return 'reports';
    }

    /**
     * The modules whose tables it reads.
     *
     * @return list<string>
     */
    public function dependsOn(): array
    {
        return ['accounts', 'customers', 'catalog'];
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
                // Each report is reached from the list of them, and sits under it.
                activeWhen: ['admin.reports.*'],
                permission: ReportPermission::View,
            ),
        ]);
    }
}
