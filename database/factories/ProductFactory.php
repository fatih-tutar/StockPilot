<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####')),
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'quantity_piece' => fake()->numberBetween(0, 200),
            'quantity_pallet' => fake()->numberBetween(0, 20),
            'low_stock_threshold' => 10,
            'is_active' => true,
        ];
    }
}
