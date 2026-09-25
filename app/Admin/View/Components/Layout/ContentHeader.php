<?php

namespace App\Admin\View\Components\Layout;

use App\Admin\Navigation\AdminNavigation;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * `<x-admin::layout.content-header />`: the screen's heading, its breadcrumbs,
 * and its actions.
 *
 * A screen that sits in the sidebar is named by it, so it does not repeat its
 * own name; a `title` overrides that.
 */
final class ContentHeader extends Component
{
    public readonly ?string $heading;

    public function __construct(
        AdminNavigation $navigation,
        ?string $title = null,
        public readonly bool $breadcrumbs = true,
    ) {
        $this->heading = $title ?? $navigation->currentTitle();
    }

    public function render(): View
    {
        return view('admin::layout.content-header');
    }
}
