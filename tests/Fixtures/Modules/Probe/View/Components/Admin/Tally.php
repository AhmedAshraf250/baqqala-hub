<?php

namespace Tests\Fixtures\Modules\Probe\View\Components\Admin;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * `<x-probe::admin.tally />`: a module component that needs data, so it has
 * a class; the template only shows what the class prepared.
 */
final class Tally extends Component
{
    public readonly int $total;

    public function __construct(int $of = 3)
    {
        $this->total = $of * 2;
    }

    public function render(): View
    {
        return view('probe::admin.tally');
    }
}
