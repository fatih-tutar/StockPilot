<?php

namespace Database\Factories;

use App\Models\Factory;
use Illuminate\Database\Eloquent\Factories\Factory as EloquentFactory;

/**
 * @extends EloquentFactory<Factory>
 */
class FactoryFactory extends EloquentFactory
{
    protected $model = Factory::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'name' => fake()->company(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->optional()->safeEmail(),
            'address' => fake()->optional()->address(),
            'labor_cost' => fake()->randomFloat(2, 0, 2000),
            'fine_labor_cost' => 0,
            'is_active' => true,
        ];
    }
}
