<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Inventory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inventory>
 */
class InventoryFactory extends Factory
{
    protected $model = Inventory::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'category_id' => Category::factory(),
            'code' => fake()->bothify('??-###'),
            'dimension_1' => '40',
            'dimension_2' => '40',
            'dimension_3' => '2',
            'density' => '1.25',
            'factory_name' => null,
        ];
    }
}
