<?php

namespace Database\Factories;

use App\Models\CatalogItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CatalogItem>
 */
class CatalogItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_code' => fake()->bothify('??-###'),
            'code' => fake()->bothify('##'),
            'model' => fake()->words(3, true),
            'quantity' => '6 METRE',
            'price' => '120 TL',
            'description' => fake()->sentence(),
            'sort_order' => 1,
        ];
    }
}
