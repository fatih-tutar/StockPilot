<?php

namespace Database\Factories;

use App\Enums\StockActivityPlace;
use App\Models\Product;
use App\Models\StockActivity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockActivity>
 */
class StockActivityFactory extends Factory
{
    protected $model = StockActivity::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'place' => StockActivityPlace::Store,
            'previous_quantity' => 4,
            'new_quantity' => 6,
            'recorded_at' => '2024-05-02 14:30:00',
        ];
    }
}
