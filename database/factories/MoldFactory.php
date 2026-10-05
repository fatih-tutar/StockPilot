<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Factory as FactoryModel;
use App\Models\Mold;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mold>
 */
class MoldFactory extends Factory
{
    protected $model = Mold::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'client_id' => Client::factory(),
            'factory_id' => FactoryModel::factory(),
            'number' => fake()->bothify('##-##'),
            'client_offer_price' => '1000',
            'factory_offer_price' => '800',
            'due_on' => now()->addMonth()->toDateString(),
            'contact_name' => 'Depo',
            'description' => null,
            'created_by_user_id' => User::factory(),
            'archived_at' => null,
        ];
    }
}
