<?php

namespace Database\Factories;

use App\Enums\LeaveStatus;
use App\Models\Leave;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Leave>
 */
class LeaveFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'start_on' => '2026-02-02',
            'return_on' => '2026-02-06',
            'leave_days' => 4,
            'status' => LeaveStatus::Pending,
            'in_office' => false,
        ];
    }
}
