<?php

namespace Database\Factories;

use App\Models\Factory as FactoryModel;
use App\Models\FactoryOrderForm;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FactoryOrderForm>
 */
class FactoryOrderFormFactory extends Factory
{
    protected $model = FactoryOrderForm::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'factory_id' => FactoryModel::factory(),
            'prepared_by_user_id' => User::factory(),
            'contact_name' => fake()->name(),
        ];
    }
}
