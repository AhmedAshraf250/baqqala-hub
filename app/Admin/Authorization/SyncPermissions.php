<?php

namespace App\Admin\Authorization;

use App\Foundation\Area\Area;
use App\Foundation\Contracts\Modules\SyncStepInterface;
use App\Foundation\Modules\ModuleInspector;
use App\Foundation\Modules\ModuleServiceProvider;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Whether the permission tables hold exactly the permissions the running code
 * defines.
 *
 * A permission in the code but not stored refuses everyone except the
 * unrestricted role — its sidebar item is hidden and its route answers 403,
 * with nothing saying why. One stored but gone from the code is a name left
 * behind by a rename. A disabled module's are neither: they stay for when it
 * comes back.
 */
final readonly class SyncPermissions implements SyncStepInterface
{
    public function __construct(
        private PermissionRegistry $permissions,
        private ModuleInspector $modules,
        private PermissionRegistrar $registrar,
    ) {}

    public function title(): string
    {
        return 'Permissions';
    }

    public function findings(?ModuleServiceProvider $module = null): array
    {
        return [
            ...array_map(static fn (string $name): array => [$name, 'in the code, not in the database'], $this->missing($module)),
            ...array_map(static fn (string $name): array => [$name, 'in the database, no longer in the code'], $this->retired($module)),
        ];
    }

    /**
     * Store the missing ones, and delete the retired ones — and with them,
     * by the tables' foreign keys, whatever still granted them.
     */
    public function fix(?ModuleServiceProvider $module = null): void
    {
        foreach ($this->missing($module) as $name) {
            Permission::findOrCreate($name, Area::Admin->guard());
        }

        $retired = $this->retired($module);

        if ($retired !== []) {
            Permission::query()->where('guard_name', Area::Admin->guard())->whereIn('name', $retired)->delete();
        }

        $this->registrar->forgetCachedPermissions();
    }

    /**
     * Defined by the running code, and not stored.
     *
     * @return list<string>
     */
    public function missing(?ModuleServiceProvider $module = null): array
    {
        return $this->belongingTo($module, array_diff($this->permissions->values(), $this->stored()));
    }

    /**
     * Stored, and defined by no code that runs or is merely disabled.
     *
     * @return list<string>
     */
    public function retired(?ModuleServiceProvider $module = null): array
    {
        $kept = $this->modules->disabledKeys();

        return $this->belongingTo($module, array_filter(
            array_diff($this->stored(), $this->permissions->values()),
            static fn (string $name): bool => ! in_array(strstr($name, '.', true), $kept, true),
        ));
    }

    /**
     * @return list<string>
     */
    private function stored(): array
    {
        if (! Schema::hasTable((string) config('permission.table_names.permissions'))) {
            return [];
        }

        $names = Permission::query()->where('guard_name', Area::Admin->guard())->pluck('name')->all();

        return array_values(array_filter($names, is_string(...)));
    }

    /**
     * @param  array<string>  $names
     * @return list<string>
     */
    private function belongingTo(?ModuleServiceProvider $module, array $names): array
    {
        $names = $module === null
            ? $names
            : array_filter($names, static fn (string $name): bool => str_starts_with($name, $module->key().'.'));

        sort($names);

        return $names;
    }
}
