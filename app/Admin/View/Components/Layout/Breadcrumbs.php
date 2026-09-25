<?php

namespace App\Admin\View\Components\Layout;

use App\Admin\Navigation\AdminNavigation;
use App\Admin\Navigation\NavigationItem;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * `<x-admin::layout.breadcrumbs />`: the trail to the current screen, from
 * the navigation tree. A screen the tree does not list has none.
 */
final class Breadcrumbs extends Component
{
    /**
     * @var list<NavigationItem>
     */
    public readonly array $trail;

    public function __construct(AdminNavigation $navigation)
    {
        $this->trail = $navigation->currentTrail();
    }

    public function shouldRender(): bool
    {
        return $this->trail !== [];
    }

    public function render(): View
    {
        return view('admin::layout.breadcrumbs');
    }
}
