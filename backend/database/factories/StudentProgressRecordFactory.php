<?php

namespace Database\Factories;

use App\Models\StudentProgressRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentProgressRecord>
 */
class StudentProgressRecordFactory extends Factory
{
    protected $model = StudentProgressRecord::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => User::factory(),
            'teacher_id' => User::factory(),
            'skill_area' => fake()->randomElement(StudentProgressRecord::SKILL_AREAS),
            'progress_summary_by_skill' => [
                StudentProgressRecord::SKILL_SPEAKING => fake()->sentence(),
                StudentProgressRecord::SKILL_LISTENING => fake()->sentence(),
            ],
            'speaking_confidence_rating' => fake()->randomElement(StudentProgressRecord::RATINGS),
            'vocabulary_progress' => fake()->sentence(),
            'grammar_development' => fake()->sentence(),
            'pronunciation_progress' => fake()->sentence(),
            'lesson_completion_count' => fake()->numberBetween(0, 50),
            'teacher_comments' => fake()->paragraph(),
            'milestone_achievements' => [
                fake()->sentence(),
            ],
            'level_movement' => fake()->randomElement(StudentProgressRecord::LEVEL_MOVEMENTS),
            'goals_completed' => [
                fake()->sentence(),
            ],
            'goals_in_progress' => [
                fake()->sentence(),
            ],
            'progress_status' => fake()->randomElement(StudentProgressRecord::STATUSES),
            'recorded_at' => now(),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}
