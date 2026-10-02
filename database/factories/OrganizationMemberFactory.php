<?php

namespace Database\Factories;

use App\Models\OrganizationMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationMember>
 */
class OrganizationMemberFactory extends Factory
{
    protected $model = OrganizationMember::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'user_id' => null,
            'name' => fake()->name(),
            'title' => 'Satış müdürü',
            'position' => fake()->unique()->numberBetween(1, 19),
        ];
    }
}
