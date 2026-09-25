<?php

namespace App\Modules\Catalog\Database\Factories;

use App\Foundation\Money\Money;
use App\Modules\Catalog\Database\Models\Category;
use App\Modules\Catalog\Database\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::headline(fake()->word().' '.fake()->word().' '.fake()->word());

        return [
            'category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('#####'),
            'sku' => config('catalog.sku_prefix').'-'.fake()->unique()->numerify('#####'),
            'description' => fake()->sentence(),
            'price' => Money::fromMinorUnits(fake()->numberBetween(500, 50000)),
            'cost_price' => Money::fromMinorUnits(fake()->numberBetween(300, 40000)),
            'stock_quantity' => fake()->numberBetween(0, 100),
            'attributes' => [
                'unit' => fake()->randomElement(['piece', 'kg', 'pack']),
            ],
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

    /**
     * A product at or below the configured low-stock threshold.
     */
    public function lowStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_quantity' => fake()->numberBetween(0, (int) config('catalog.low_stock_threshold')),
        ]);
    }
}
