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
            'client_id' => Client::factory(),
            'quote_id' => null,
            'user_id' => User::factory(),
            'prepared_by' => null,
            'invoiced_by' => null,
            'status' => ShipmentStatus::Received,
            'ship_date' => now()->toDateString(),
            'delivery_date' => null,
            'vehicle_plate' => null,
            'driver_name' => null,
            'shipping_address' => null,
            'notes' => null,
        ];
    }
}
