<?php

namespace Database\Factories;

use App\Enums\ShipmentStatus;
use App\Models\Client;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shipment>
 */
class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    public function definition(): array
    {
        return [
            'number' => 'S-'.now()->year.'-'.fake()->unique()->numerify('####'),
            'client_id' => Client::factory(),
            'quote_id' => null,
            'user_id' => User::factory(),
            'status' => ShipmentStatus::Draft,
            'ship_date' => now()->toDateString(),
            'delivery_date' => null,
            'vehicle_plate' => strtoupper(fake()->bothify('?? ###')),
            'driver_name' => fake()->name(),
            'shipping_address' => fake()->address(),
            'notes' => null,
        ];
    }
}
