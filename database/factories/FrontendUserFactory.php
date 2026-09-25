<?php

namespace Database\Factories;

use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\FrontendUser;

/**
 * Builds logins the frontend guard can actually see.
 */
class FrontendUserFactory extends UserFactory
{
    protected $model = FrontendUser::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            ...parent::definition(),
            'area' => Area::Frontend,
        ];
    }
}
