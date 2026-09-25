<?php

namespace Database\Seeders;

use App\Admin\Authorization\SyncPermissions;
use App\Admin\Authorization\SyncRoleGrants;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Brings the permission tables in line with the running modules and
 * `config/roles.php`, without asking.
 *
 * The same two steps `php artisan app:sync` shows and then fixes on
 * approval — one implementation, so the seeder and the sync command cannot
 * disagree. Safe to re-run: what is missing is stored, what is retired is
 * removed, and every role the file names holds exactly what it gives it from
 * the running modules. A disabled module's permission records stay for when
 * it comes back; no role goes on granting them meanwhile.
 */
class AuthorizationSeeder extends Seeder
{
    public function __construct(
        private readonly SyncPermissions $permissions,
        private readonly SyncRoleGrants $roles,
    ) {}

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->permissions->fix();
            $this->roles->fix();
        });
    }
}
