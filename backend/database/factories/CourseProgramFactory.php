<?php

namespace Database\Factories;

use App\Models\CourseProgram;
use App\Models\CourseType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CourseProgram>
 */
class CourseProgramFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'course_type_id' => CourseType::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 9999),
            'description' => fake()->paragraph(),
            'placement_level' => fake()->randomElement(['A1', 'A2', 'B1', 'B2', 'C1']),
            'number_of_sessions' => fake()->numberBetween(4, 24),
            'lesson_structure' => [
                'format' => 'one_to_one',
                'session_length_minutes' => 50,
                'focus_areas' => fake()->randomElements(['speaking', 'listening', 'grammar', 'vocabulary', 'writing'], 3),
            ],
            'milestones' => [
                ['session' => 1, 'goal' => 'Initial needs review'],
                ['session' => 4, 'goal' => 'Progress checkpoint'],
            ],
            'is_archived' => false,
            'archived_at' => null,
            'archived_by' => null,
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }

    public function archived(?User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'is_archived' => true,
            'archived_at' => now(),
            'archived_by' => $user?->id ?? User::factory(),
        ]);
    }
}
