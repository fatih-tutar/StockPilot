<?php

namespace Database\Factories;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'name' => fake()->randomElement(['Kia', 'Toyota', 'Iveco']),
            'license_plate' => fake()->bothify('34 ?? ####'),
            'driver_name' => fake()->name(),
            'description' => null,
            'is_delivery_vehicle' => false,
            'casco_expires_on' => now()->addYear()->toDateString(),
            'insurance_expires_on' => now()->addYear()->toDateString(),
            'inspection_due_on' => now()->addMonths(6)->toDateString(),
        ];
    }
}
