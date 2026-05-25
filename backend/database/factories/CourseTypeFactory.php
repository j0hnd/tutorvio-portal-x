<?php

namespace Database\Factories;

use App\Models\CourseType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CourseType>
 */
class CourseTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'General English',
            'Business English',
            'Maturita Preparation',
            'Sunshine Restart Program',
            'English for Work Confidence',
            'Interview Preparation',
            'Travel English',
        ]).' '.fake()->unique()->numberBetween(1, 9999);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->paragraph(),
            'sort_order' => fake()->numberBetween(1, 50),
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
