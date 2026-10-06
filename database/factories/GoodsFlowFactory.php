<?php

namespace Database\Factories;

use App\Models\GoodsFlow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoodsFlow>
 */
class GoodsFlowFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recorded_on' => '2026-03-02',
            'store_incoming' => 0,
            'store_outgoing' => 0,
            'warehouse_incoming' => 0,
            'warehouse_outgoing' => 0,
        ];
    }
}
