<?php

namespace Database\Factories;

use App\Models\CourseProgram;
use App\Models\CourseType;
use App\Models\LessonRecord;
use App\Models\TeacherCompensation;
use App\Models\TeacherCompensationRateRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeacherCompensationRateRule>
 */
class TeacherCompensationRateRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'teacher_compensation_id' => TeacherCompensation::factory(),
            'lesson_type' => fake()->optional()->randomElement(LessonRecord::LESSON_TYPES),
            'experience_level' => fake()->optional()->randomElement(['junior', 'mid', 'senior']),
            'contract_agreement' => fake()->optional()->randomElement(['standard', 'premium', 'contractor']),
            'course_type_id' => null,
            'course_program_id' => null,
            'pay_model' => fake()->optional()->randomElement(TeacherCompensation::PAY_MODELS),
            'pay_rate' => fake()->randomFloat(2, 15, 100),
            'currency' => null,
            'priority' => fake()->numberBetween(0, 100),
            'is_active' => true,
            'internal_admin_notes' => fake()->optional()->sentence(),
        ];
    }

    public function forCourseType(?CourseType $courseType = null): static
    {
        return $this->state(fn (array $attributes) => [
            'course_type_id' => $courseType?->id ?? CourseType::factory(),
        ]);
    }

    public function forCourseProgram(?CourseProgram $courseProgram = null): static
    {
        return $this->state(fn (array $attributes) => [
            'course_program_id' => $courseProgram?->id ?? CourseProgram::factory(),
        ]);
    }
}
