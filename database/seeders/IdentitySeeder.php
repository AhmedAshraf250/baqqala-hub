<?php

namespace Database\Seeders;

use App\Admin\Authorization\RoleDefinitions;
use App\Foundation\Identity\Models\AdminUser;
use App\Foundation\Identity\Models\FrontendUser;
use Illuminate\Database\Seeder;

/**
 * One login per area, so both shells can be opened after a fresh install.
 *
 * Each is created through its own area's model, which is what sets the area:
 * `area` is not fillable, and nothing here names it.
 */
class IdentitySeeder extends Seeder
{
    public function __construct(private readonly RoleDefinitions $roles) {}

    public function run(): void
    {
        // The first account owns the shop; everything else is granted from
        // inside the panel.
        $owner = AdminUser::query()->firstWhere('email', 'admin@example.test')
            ?? AdminUser::factory()->create(['name' => 'Administrator', 'email' => 'admin@example.test']);

        $owner->syncRoles($this->roles->unrestricted());

        FrontendUser::query()->firstWhere('email', 'customer@example.test')
            ?? FrontendUser::factory()->create(['name' => 'Customer', 'email' => 'customer@example.test']);
    }
}
