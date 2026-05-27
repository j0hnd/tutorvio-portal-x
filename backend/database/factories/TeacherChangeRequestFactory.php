<?php

namespace Database\Factories;

use App\Models\TeacherChangeRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeacherChangeRequest>
 */
class TeacherChangeRequestFactory extends Factory
{
    protected $model = TeacherChangeRequest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => User::factory(),
            'current_teacher_id' => User::factory(),
            'requested_reason' => fake()->sentence(),
            'preferred_schedule_notes' => fake()->optional()->sentence(),
            'status' => TeacherChangeRequest::STATUS_PENDING,
        ];
    }
}
