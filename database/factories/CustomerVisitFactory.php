<?php

namespace Database\Factories;

use App\Models\CustomerVisit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerVisit>
 */
class CustomerVisitFactory extends Factory
{
    protected $model = CustomerVisit::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'customer_visit_category_id' => null,
            'city' => 'İstanbul',
            'district' => 'Kağıthane',
            'customer_name' => fake()->company(),
            'contact_name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'visited_on' => now()->toDateString(),
            'planned_on' => now()->addWeek()->toDateString(),
            'address' => fake()->address(),
            'notes' => null,
        ];
    }
}
