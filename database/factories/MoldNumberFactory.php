<?php

namespace Database\Factories;

use App\Models\Factory as FactoryModel;
use App\Models\MoldNumber;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MoldNumber>
 */
class MoldNumberFactory extends Factory
{
    protected $model = MoldNumber::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'factory_id' => FactoryModel::factory(),
            'number' => fake()->bothify('K-###'),
        ];
    }
}
