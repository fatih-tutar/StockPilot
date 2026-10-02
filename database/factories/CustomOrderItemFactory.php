<?php

namespace Database\Factories;

use App\Models\CustomOrder;
use App\Models\CustomOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomOrderItem>
 */
class CustomOrderItemFactory extends Factory
{
    protected $model = CustomOrderItem::class;

    public function definition(): array
    {
        return [
            'custom_order_id' => CustomOrder::factory(),
            'factory_id' => null,
            'product_name' => fake()->words(3, true),
            'length' => '6 metre',
            'quantity' => 10,
            'unit_price' => 180,
            'due_on' => now()->addDays(14)->toDateString(),
        ];
    }
}
