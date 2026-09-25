<?php

namespace Database\Factories;

use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\AdminUser;

/**
 * Builds logins the admin guard can actually see.
 *
 * The area is forced, because an `AdminUser` row with any other area would be
 * invisible to its own global scope the moment it was saved.
 */
class AdminUserFactory extends UserFactory
{
    protected $model = AdminUser::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            ...parent::definition(),
            'area' => Area::Admin,
        ];
    }
}
