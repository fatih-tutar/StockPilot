<?php

namespace Database\Factories;

use App\Models\CustomerVisitCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerVisitCategory>
 */
class CustomerVisitCategoryFactory extends Factory
{
    protected $model = CustomerVisitCategory::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'name' => fake()->unique()->words(2, true),
        ];
    }
}
