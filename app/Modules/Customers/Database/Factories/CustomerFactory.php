<?php

namespace App\Modules\Customers\Database\Factories;

use App\Foundation\Identity\Models\User;
use App\Modules\Customers\Database\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'name' => fake()->name(),
            'phone' => fake()->unique()->phoneNumber(),
            'address' => fake()->address(),
            'notes' => null,
        ];
    }

    /**
     * A customer who can sign in to the customer area.
     */
    public function withLogin(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => User::factory()->frontend(),
        ]);
    }
}
