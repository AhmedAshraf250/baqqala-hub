<?php

namespace App\Admin\Overlays;

use App\Admin\Contracts\Overlays\ProvidesAdminOverlaysInterface;
use App\Foundation\Area\Area;
use App\Foundation\Modules\ModuleRegistry;
use Illuminate\Container\Attributes\Scoped;

/**
 * What the running modules keep on every admin screen — the drawer that
 * records an entry, the notebook's quick note — for this administrator.
 *
 * It knows nothing of what they are. A module names them through
 * {@see ProvidesAdminOverlaysInterface}; the layout places each one.
 */
#[Scoped]
final class AdminOverlays
{
    /**
     * The components this administrator may have, worked out once per request.
     *
     * @var list<string>|null
     */
    private ?array $components = null;

    public function __construct(private readonly ModuleRegistry $modules) {}

    /**
     * @return list<string>
     */
    public function components(): array
    {
        return $this->components ??= array_values($this->modules
            ->providing(ProvidesAdminOverlaysInterface::class)
            ->flatMap(static fn (ProvidesAdminOverlaysInterface $module): array => $module->adminOverlays())
            ->filter(static fn (AdminOverlay $overlay): bool => $overlay->permission === null
                || Area::Admin->user()?->can($overlay->permission->value) === true)
            ->map(static fn (AdminOverlay $overlay): string => $overlay->component)
            ->all());
    }
}
