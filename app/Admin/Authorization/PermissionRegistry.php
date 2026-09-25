<?php

namespace App\Admin\Authorization;

use App\Admin\Contracts\Authorization\PermissionInterface;
use App\Admin\Contracts\Authorization\ProvidesPermissionsInterface;
use App\Foundation\Modules\ModuleRegistry;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Support\Collection;

/**
 * Every permission this installation recognises.
 *
 * The shell's own set plus whatever the running modules contribute, so
 * disabling a module drops its permissions from what is checked and seeded,
 * and nothing central is edited.
 */
#[Scoped]
final class PermissionRegistry
{
    /**
     * Assembled once per request; a permissions screen and the seeder both
     * read it several times.
     *
     * @var Collection<int, PermissionInterface>|null
     */
    private ?Collection $permissions = null;

    public function __construct(private readonly ModuleRegistry $modules) {}

    /**
     * @return Collection<int, PermissionInterface>
     */
    public function all(): Collection
    {
        return $this->permissions ??= collect(AdminPermission::cases())
            ->concat(
                $this->modules
                    ->providing(ProvidesPermissionsInterface::class)
                    ->flatMap(static fn (ProvidesPermissionsInterface $module): array => $module->permissions()),
            )
            ->values();
    }

    /**
     * The permission strings, as the permission tables store them.
     *
     * @return list<string>
     */
    public function values(): array
    {
        return array_values(
            $this->all()
                ->map(static fn (PermissionInterface $permission): string => $permission->value)
                ->all(),
        );
    }

    /**
     * Grouped by the heading they are shown under, for a permissions screen.
     *
     * @return Collection<string, Collection<int, PermissionInterface>>
     */
    public function grouped(): Collection
    {
        return $this->all()->groupBy(static fn (PermissionInterface $permission): string => $permission->group());
    }
}
