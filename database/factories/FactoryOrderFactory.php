<?php

namespace Database\Factories;

use App\Enums\FactoryOrderStatus;
use App\Models\Factory as FactoryModel;
use App\Models\FactoryOrder;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FactoryOrder>
 */
class FactoryOrderFactory extends Factory
{
    protected $model = FactoryOrder::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'factory_id' => FactoryModel::factory(),
            'product_id' => Product::factory(),
            'factory_order_form_id' => null,
            'prepared_by_user_id' => User::factory(),
            'product_name' => fake()->words(3, true),
            'contact_name' => fake()->name(),
            'quantity' => fake()->numberBetween(1, 100),
            'length' => '6.00',
            'pallet_count' => 0,
            'due_on' => now()->addWeek()->toDateString(),
            'status' => FactoryOrderStatus::Open,
        ];
    }
}
