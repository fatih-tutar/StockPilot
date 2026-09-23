<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'parent_id' => null,
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'sort_order' => fake()->numberBetween(0, 50),
        ];
    }
}
