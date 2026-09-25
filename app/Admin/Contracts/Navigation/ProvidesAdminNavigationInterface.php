<?php

namespace App\Admin\Contracts\Navigation;

use App\Admin\Navigation\NavigationSection;

/**
 * A module that appears in the admin sidebar.
 *
 * The section it returns is unfiltered; the shell drops what the signed-in
 * administrator may not see. A module states what it *has*, never who may see
 * it — that is the shell's job, and keeping it there is why one permission
 * rule covers every module.
 */
interface ProvidesAdminNavigationInterface
{
    public function adminNavigation(): NavigationSection;
}
