<?php

namespace Database\Factories;

use App\Models\Homework;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Homework>
 */
class HomeworkFactory extends Factory
{
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
            'lesson_id' => function (array $attributes): int {
                return Lesson::create([
                    'student_id' => $attributes['student_id'],
                    'teacher_id' => $attributes['teacher_id'],
                    'start_time' => now()->addDay()->startOfHour(),
                    'end_time' => now()->addDay()->startOfHour()->addHour(),
                    'status' => Lesson::STATUS_SCHEDULED,
                ])->id;
            },
            'title' => fake()->sentence(4),
            'instructions' => fake()->paragraph(),
            'due_date' => fake()->date('Y-m-d', '+2 weeks'),
            'status' => Homework::STATUS_ASSIGNED,
            'teacher_feedback' => null,
            'completed_at' => null,
            'reviewed_at' => null,
            'attachment_links' => null,
        ];
    }
}
