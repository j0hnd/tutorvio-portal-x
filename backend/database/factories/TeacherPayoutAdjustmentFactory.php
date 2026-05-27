<?php

namespace Database\Factories;

use App\Models\TeacherPayoutAdjustment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeacherPayoutAdjustment>
 */
class TeacherPayoutAdjustmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'teacher_id' => User::factory(),
            'payout_period_id' => null,
            'type' => fake()->randomElement(TeacherPayoutAdjustment::TYPES),
            'amount' => fake()->randomFloat(2, 5, 150),
            'currency' => 'USD',
            'reason' => fake()->sentence(),
            'internal_notes' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
