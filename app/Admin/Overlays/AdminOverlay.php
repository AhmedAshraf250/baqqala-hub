<?php

namespace App\Admin\Overlays;

use App\Admin\Contracts\Authorization\PermissionInterface;

/**
 * One component a module keeps on every admin screen: its Livewire name, and
 * what one must hold to have it at all.
 */
final readonly class AdminOverlay
{
    public function __construct(
        public string $component,
        public ?PermissionInterface $permission = null,
    ) {}
}
