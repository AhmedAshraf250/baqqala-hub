<?php

namespace App\Admin\Authorization;

use App\Foundation\Area\Area;
use App\Foundation\Contracts\Modules\UninstallStepInterface;
use App\Foundation\Modules\ModuleServiceProvider;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Removes a module's permissions when it is uninstalled.
 *
 * The admin shell holds them, so the admin shell removes them. Every
 * permission a module defines starts with its key — a test holds that — so
 * `{key}.*` is exactly the module's own, and deleting a permission takes its
 * grants to roles and administrators with it, by the tables' foreign keys.
 */
final class RemoveModulePermissions implements UninstallStepInterface
{
    public function describe(ModuleServiceProvider $module): array
    {
        $permissions = $this->permissionsOf($module)->pluck('name');

        if ($permissions->isEmpty()) {
            return [];
        }

        $roles = Role::query()
            ->whereHas('permissions', fn (Builder $query) => $query->whereIn('name', $permissions))
            ->pluck('name');

        return [sprintf(
            '%d permissions (%s)%s',
            $permissions->count(),
            $permissions->implode(', '),
            $roles->isEmpty() ? '' : ', granted to '.$roles->implode(', '),
        )];
    }

    public function run(ModuleServiceProvider $module): void
    {
        $this->permissionsOf($module)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return Builder<Permission>
     */
    private function permissionsOf(ModuleServiceProvider $module): Builder
    {
        return Permission::query()
            ->where('guard_name', Area::Admin->guard())
            ->where('name', 'like', $module->key().'.%');
    }
}
