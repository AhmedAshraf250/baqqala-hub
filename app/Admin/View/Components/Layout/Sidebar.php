<?php

namespace App\Admin\View\Components\Layout;

use App\Admin\Navigation\AdminNavigation;
use App\Admin\Navigation\NavigationSection;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * `<x-admin::layout.sidebar />`: the sections this administrator may reach.
 */
final class Sidebar extends Component
{
    /**
     * @var list<NavigationSection>
     */
    public readonly array $sections;

    public function __construct(AdminNavigation $navigation)
    {
        $this->sections = $navigation->sections();
    }

    public function render(): View
    {
        return view('admin::layout.sidebar');
    }
}
