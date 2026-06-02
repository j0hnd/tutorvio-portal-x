<?php

namespace Database\Factories;

use App\Models\TeacherCompensation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeacherCompensation>
 */
class TeacherCompensationFactory extends Factory
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
            'pay_model' => fake()->randomElement(TeacherCompensation::PAY_MODELS),
            'default_pay_rate' => fake()->randomFloat(2, 10, 80),
            'currency' => 'USD',
            'effective_start_date' => now()->toDateString(),
            'effective_end_date' => null,
            'internal_admin_notes' => fake()->optional()->sentence(),
        ];
    }
}
