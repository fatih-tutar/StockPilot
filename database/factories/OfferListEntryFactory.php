<?php

namespace Database\Factories;

use App\Enums\OfferListStatus;
use App\Models\OfferListEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OfferListEntry>
 */
class OfferListEntryFactory extends Factory
{
    protected $model = OfferListEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_name' => fake()->company(),
            'contact_name' => fake()->name(),
            'product_quantity' => fake()->sentence(3),
            'price' => fake()->randomFloat(2, 10, 1000),
            'factory_name' => fake()->company(),
            'factory_price' => fake()->randomFloat(2, 10, 1000),
            'offered_by_user_id' => User::factory(),
            'notes' => null,
            'offered_at' => now(),
            'status' => OfferListStatus::Open,
        ];
    }
}
