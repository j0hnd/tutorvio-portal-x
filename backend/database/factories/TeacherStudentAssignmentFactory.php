<?php

namespace Database\Factories;

use App\Models\TeacherStudentAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeacherStudentAssignment>
 */
class TeacherStudentAssignmentFactory extends Factory
{
    protected $model = TeacherStudentAssignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => User::factory(),
            'teacher_id' => User::factory(),
            'assigned_by' => User::factory(),
            'assigned_at' => now(),
            'status' => TeacherStudentAssignment::STATUS_ACTIVE,
            'reason' => fake()->sentence(),
            'notes' => fake()->sentence(),
        ];
    }
}
