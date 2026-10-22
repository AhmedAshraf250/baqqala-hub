<?php

namespace App\Admin\View\Components\Layout;

use App\Admin\Overlays\AdminOverlays;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * `<x-admin::layout.overlays />`: what the running modules keep on every
 * screen for this administrator, each closed until something opens it.
 */
final class Overlays extends Component
{
    /**
     * @var list<string>
     */
    public readonly array $components;

    public function __construct(AdminOverlays $overlays)
    {
        $this->components = $overlays->components();
    }

    public function shouldRender(): bool
    {
        return $this->components !== [];
    }

    public function render(): View
    {
        return view('admin::layout.overlays');
    }
}
