<?php

namespace Database\Factories;

use App\Enums\WorkTaskStatus;
use App\Models\WorkTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkTask>
 */
class WorkTaskFactory extends Factory
{
    protected $model = WorkTask::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'title' => fake()->sentence(4),
            'due_on' => now()->addWeek()->toDateString(),
            'repeats_monthly' => false,
            'status' => WorkTaskStatus::Open,
        ];
    }
}
