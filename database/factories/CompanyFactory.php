<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'letterhead' => fake()->optional()->address(),
            'logo' => null,
            'price_list_visible' => true,
            'usd_rate' => fake()->randomFloat(4, 30, 50),
            'lme_rate' => fake()->randomFloat(2, 2000, 4000),
        ];
    }
}
