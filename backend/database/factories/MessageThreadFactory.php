<?php

namespace Database\Factories;

use App\Models\MessageThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessageThread>
 */
class MessageThreadFactory extends Factory
{
    protected $model = MessageThread::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'thread_type' => MessageThread::TYPE_STUDENT_TEACHER,
            'status' => MessageThread::STATUS_ACTIVE,
            'student_id' => User::factory(),
            'teacher_id' => User::factory(),
            'created_by' => User::factory(),
            'last_message_at' => now(),
            'is_archived' => false,
            'archived_at' => null,
            'archived_by' => null,
            'metadata' => null,
        ];
    }
}
