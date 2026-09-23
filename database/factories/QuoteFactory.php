<?php

namespace Database\Factories;

use App\Enums\QuoteStatus;
use App\Models\Client;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    protected $model = Quote::class;

    public function definition(): array
    {
        return [
            'number' => 'Q-'.now()->year.'-'.fake()->unique()->numerify('####'),
            'client_id' => Client::factory(),
            'user_id' => User::factory(),
            'status' => QuoteStatus::Draft,
            'quote_date' => now()->toDateString(),
            'valid_until' => now()->addDays(14)->toDateString(),
            'currency' => 'TRY',
            'tax_rate' => 20,
            'subtotal' => 0,
            'tax_amount' => 0,
            'total' => 0,
            'notes' => null,
        ];
    }
}
