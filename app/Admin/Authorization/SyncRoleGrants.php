<?php

namespace App\Admin\Authorization;

use App\Foundation\Area\Area;
use App\Foundation\Contracts\Modules\SyncStepInterface;
use App\Foundation\Modules\ModuleServiceProvider;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Whether each role in `config/roles.php` holds exactly what the file gives it
 * from the running modules.
 *
 * The file grants by pattern — `customers.*` — so a permission a module adds
 * belongs to every role whose pattern covers it, but only once it is stored
 * and granted. No role goes on holding what no running module defines: a
 * disabled module's grants are taken off, and given back from the file when
 * it is enabled again. Roles an owner created in the panel are not the
 * file's, and are left alone.
 */
final readonly class SyncRoleGrants implements SyncStepInterface
{
    public function __construct(
        private RoleDefinitions $roles,
        private PermissionRegistry $permissions,
        private PermissionRegistrar $registrar,
    ) {}

    public function title(): string
    {
        return 'Roles, against config/roles.php';
    }

    public function findings(?ModuleServiceProvider $module = null): array
    {
        if (! Schema::hasTable((string) config('permission.table_names.roles'))) {
            return [];
        }

        $running = $this->permissions->values();
        $findings = [];

        foreach ($this->differences($module) as $role => [$give, $take, $exists]) {
            if (! $exists) {
                $findings[] = [$role, 'in config/roles.php, not in the database'];
            }

            foreach ($give as $permission) {
                $findings[] = [$role, "should have {$permission}"];
            }

            foreach ($take as $permission) {
                $findings[] = [$role, in_array($permission, $running, true)
                    ? "has {$permission}, which config/roles.php does not give it"
                    : "has {$permission}, which no running module defines"];
            }
        }

        return $findings;
    }

    /**
     * Create each role the file names, give it what it lacks, and take off
     * what it should not hold.
     */
    public function fix(?ModuleServiceProvider $module = null): void
    {
        // Permissions stored a moment ago are not in the registrar's cache yet,
        // and grants are looked up through it.
        $this->registrar->forgetCachedPermissions();

        foreach ($this->differences($module) as $role => [$give, $take]) {
            $stored = Role::findOrCreate($role, Area::Admin->guard());

            if ($give !== []) {
                $stored->givePermissionTo($give);
            }

            if ($take !== []) {
                $stored->revokePermissionTo($take);
            }
        }

        $this->registrar->forgetCachedPermissions();
    }

    /**
     * For each role the file names: what to give, what to take off, and
     * whether the role exists — one module's permissions only, when one is
     * given.
     *
     * @return array<string, array{0: list<string>, 1: list<string>, 2: bool}>
     */
    private function differences(?ModuleServiceProvider $module): array
    {
        $stored = Role::query()->where('guard_name', Area::Admin->guard())->with('permissions')->get()->keyBy('name');
        $differences = [];

        foreach ($this->roles->all() as $role => $granted) {
            $holds = $stored->has($role) ? $stored[$role]->permissions->pluck('name')->all() : [];

            $granted = $this->concerning($module, $granted);
            $holds = $this->concerning($module, array_filter($holds, is_string(...)));

            $give = array_values(array_diff($granted, $holds));
            $take = array_values(array_diff($holds, $granted));

            if ($give !== [] || $take !== [] || ! $stored->has($role)) {
                $differences[$role] = [$give, $take, $stored->has($role)];
            }
        }

        return $differences;
    }

    /**
     * @param  array<string>  $permissions
     * @return list<string>
     */
    private function concerning(?ModuleServiceProvider $module, array $permissions): array
    {
        return array_values($module === null
            ? $permissions
            : array_filter($permissions, static fn (string $name): bool => str_starts_with($name, $module->key().'.')));
    }
}
