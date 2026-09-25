<?php

namespace App\Admin\Console;

use App\Admin\Authorization\PermissionRegistry;
use App\Admin\Authorization\RoleDefinitions;
use App\Admin\Authorization\SyncPermissions;

/**
 * The admin shell's section of `php artisan about`: its roles, and whether
 * the permissions the running modules define are all stored.
 *
 *     php artisan about --only=admin_area
 */
final readonly class AboutAdminArea
{
    public function __construct(
        private RoleDefinitions $roles,
        private PermissionRegistry $permissions,
        private SyncPermissions $stored,
    ) {}

    /**
     * @return array<string, string>
     */
    public function __invoke(): array
    {
        $unrestricted = $this->roles->unrestricted();

        $roles = [];

        foreach ($this->roles->all() as $role => $granted) {
            $roles[] = $role === $unrestricted ? "{$role} (everything)" : sprintf('%s (%d)', $role, count($granted));
        }

        return [
            'Roles' => implode(', ', $roles),
            'Permissions' => $this->permissionsStored(),
        ];
    }

    /**
     * How many permissions the modules define, and whether the permission
     * tables hold them — a module enabled without reseeding does not.
     */
    private function permissionsStored(): string
    {
        $defined = count($this->permissions->values());
        $missing = count($this->stored->missing());

        return $missing === 0
            ? "{$defined} defined, all stored"
            : sprintf('%d defined, <fg=yellow>%d not stored</> — see php artisan app:sync', $defined, $missing);
    }
}
