<?php

namespace App\Modules\Customers\Database\Factories;

use App\Modules\Customers\Contracts\Enums\GroupColor;
use App\Modules\Customers\Database\Models\CustomerGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerGroup>
 */
class CustomerGroupFactory extends Factory
{
    protected $model = CustomerGroup::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'color' => fake()->randomElement(GroupColor::cases()),
            'icon' => null,
            'sort_order' => 0,
            'archived_at' => null,
        ];
    }

    /**
     * A group the owner put away: hidden, its members kept.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }
}
