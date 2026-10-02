<?php

namespace Database\Factories;

use App\Enums\CustomOrderStatus;
use App\Enums\DeliveryMethod;
use App\Models\CustomOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomOrder>
 */
class CustomOrderFactory extends Factory
{
    protected $model = CustomOrder::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'client_id' => null,
            'user_id' => null,
            'delivery_method' => DeliveryMethod::WeShip,
            'status' => CustomOrderStatus::Open,
            'notes' => null,
            'ordered_at' => now(),
        ];
    }
}
