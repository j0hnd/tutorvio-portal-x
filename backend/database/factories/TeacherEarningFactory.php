<?php

namespace Database\Factories;

use App\Models\LessonRecord;
use App\Models\TeacherCompensation;
use App\Models\TeacherEarning;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeacherEarning>
 */
class TeacherEarningFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $rate = fake()->randomFloat(2, 15, 75);
        $quantity = fake()->randomFloat(2, 1, 3);

        return [
            'teacher_id' => User::factory(),
            'lesson_record_id' => null,
            'source_type' => TeacherEarning::SOURCE_LESSON_RECORD,
            'source_id' => fake()->unique()->numberBetween(1, 100000),
            'pay_model' => fake()->randomElement(TeacherCompensation::PAY_MODELS),
            'rate_used' => $rate,
            'quantity' => $quantity,
            'amount' => round($rate * $quantity, 2),
            'currency' => 'USD',
            'calculation_metadata' => [],
            'status' => TeacherEarning::STATUS_PENDING,
        ];
    }

    public function forLessonRecord(LessonRecord $lessonRecord): static
    {
        return $this->state(fn (array $attributes) => [
            'teacher_id' => $lessonRecord->teacher_id,
            'lesson_record_id' => $lessonRecord->id,
            'source_type' => TeacherEarning::SOURCE_LESSON_RECORD,
            'source_id' => $lessonRecord->id,
            'calculation_metadata' => [
                ...($attributes['calculation_metadata'] ?? []),
                'lesson_type' => $lessonRecord->lesson_type,
                'scheduled_date' => $lessonRecord->scheduled_date?->toDateString(),
            ],
        ]);
    }
}
