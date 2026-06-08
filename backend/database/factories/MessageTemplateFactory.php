<?php

namespace Database\Factories;

use App\Models\MessageTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessageTemplate>
 */
class MessageTemplateFactory extends Factory
{
    protected $model = MessageTemplate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->randomElement([
                'Lesson reminder',
                'Homework reminder',
                'Reschedule notice',
                'Attendance follow-up',
                'Progress check-in',
            ]),
            'body' => fake()->paragraph(),
            'category' => fake()->randomElement(MessageTemplate::CATEGORIES),
            'role_visibility' => [MessageTemplate::ROLE_TEACHER],
            'status' => MessageTemplate::STATUS_ACTIVE,
        ];
    }
}
