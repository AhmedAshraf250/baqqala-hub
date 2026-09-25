<?php

namespace App\Admin\View\Components\Layout;

use App\Admin\Navigation\AdminNavigation;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * `<x-admin::layout.page-title />`: the browser tab's `<title>` — the screen's
 * name, as the content header shows it, then the product's.
 */
final class PageTitle extends Component
{
    public readonly string $text;

    public function __construct(AdminNavigation $navigation, ?string $title = null)
    {
        $heading = $title ?? $navigation->currentTitle();
        $brand = (string) __('shell.brand.name');

        $this->text = filled($heading) ? $heading.' — '.$brand : $brand;
    }

    public function render(): View
    {
        return view('admin::layout.page-title');
    }
}
