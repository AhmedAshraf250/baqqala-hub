<?php

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Database\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::headline(fake()->word().' '.fake()->word());

        return [
            'parent_id' => null,
            'name' => $name,
            // The slug carries the uniqueness, the way a real catalog does:
            // two shelves can share a display name, never a URL.
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('#####'),
            'image' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
