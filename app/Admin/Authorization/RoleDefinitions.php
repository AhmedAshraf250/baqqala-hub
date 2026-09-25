<?php

namespace App\Admin\Authorization;

use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\AdminUser;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * The roles an installation starts with, read from `config/roles.php`.
 *
 * Roles are the product's opinion — a shop has an owner, a manager, and a
 * cashier; a school would not — so they live in configuration beside
 * `config/modules.php`, where the product is assembled. Code in the shell
 * never names a module's permission to express them.
 *
 * Grants are patterns over permission names (`catalog.*`). A pattern that
 * matches a module this installation does not run grants nothing, which is
 * what lets one roles file serve installations with different modules.
 */
#[Scoped]
final readonly class RoleDefinitions
{
    public function __construct(private PermissionRegistry $permissions) {}

    /**
     * Every role, with the permission names it is granted.
     *
     * @return array<string, list<string>>
     */
    public function all(): array
    {
        $available = $this->permissions->values();
        $roles = [];

        foreach ($this->patterns() as $role => $patterns) {
            $roles[$role] = array_values(array_filter(
                $available,
                static fn (string $permission): bool => Str::is($patterns, $permission),
            ));
        }

        return $roles;
    }

    /**
     * The role granted everything without being checked.
     */
    public function unrestricted(): string
    {
        return (string) config('roles.unrestricted');
    }

    /**
     * What to call an administrator's position — the role they hold.
     *
     * A role the product ships with has a translation; one an owner created
     * from the panel shows its own name; an administrator with no role at all
     * is simply an administrator.
     */
    public function labelFor(AdminUser $administrator): string
    {
        $role = $administrator->getRoleNames()->first();

        if (! is_string($role)) {
            return Area::Admin->label();
        }

        return Lang::has("shell.roles.{$role}") ? __("shell.roles.{$role}") : Str::headline($role);
    }

    /**
     * The grant patterns as configured, before they are matched.
     *
     * @return array<string, list<string>>
     */
    public function patterns(): array
    {
        /** @var array<string, list<string>> */
        return config('roles.defaults', []);
    }
}
