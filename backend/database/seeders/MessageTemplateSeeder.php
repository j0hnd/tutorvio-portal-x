<?php

namespace Database\Seeders;

use App\Models\MessageTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;

class MessageTemplateSeeder extends Seeder
{
    /**
     * Seed reusable chat quick message templates.
     */
    public function run(): void
    {
        $adminId = User::query()->where('email', 'admin@example.com')->value('id');

        $templates = [
            [
                'title' => 'Lesson reminder',
                'body' => 'Hi! This is a quick reminder about your upcoming lesson. Please be ready a few minutes before the scheduled start time.',
                'category' => MessageTemplate::CATEGORY_LESSON_REMINDER,
                'role_visibility' => [MessageTemplate::ROLE_ADMIN, MessageTemplate::ROLE_STAFF, MessageTemplate::ROLE_TEACHER],
            ],
            [
                'title' => 'Homework reminder',
                'body' => 'Hi! Please remember to complete the assigned homework before your next lesson.',
                'category' => MessageTemplate::CATEGORY_HOMEWORK_REMINDER,
                'role_visibility' => [MessageTemplate::ROLE_ADMIN, MessageTemplate::ROLE_STAFF, MessageTemplate::ROLE_TEACHER],
            ],
            [
                'title' => 'Reschedule notice',
                'body' => 'Hi! We need to reschedule this lesson. Please send your available times so we can confirm a new schedule.',
                'category' => MessageTemplate::CATEGORY_RESCHEDULE_NOTICE,
                'role_visibility' => [MessageTemplate::ROLE_ADMIN, MessageTemplate::ROLE_STAFF, MessageTemplate::ROLE_TEACHER],
            ],
            [
                'title' => 'Payment reminder',
                'body' => 'Hi! This is a reminder to check your current invoice or payment status. Please contact the admin team if you have questions.',
                'category' => MessageTemplate::CATEGORY_PAYMENT_REMINDER,
                'role_visibility' => [MessageTemplate::ROLE_ADMIN, MessageTemplate::ROLE_STAFF],
            ],
            [
                'title' => 'Attendance follow-up',
                'body' => 'Hi! We noticed a missed or late attendance record. Please let us know if there was an issue joining the class.',
                'category' => MessageTemplate::CATEGORY_ATTENDANCE_FOLLOW_UP,
                'role_visibility' => [MessageTemplate::ROLE_ADMIN, MessageTemplate::ROLE_STAFF, MessageTemplate::ROLE_TEACHER],
            ],
            [
                'title' => 'Progress check-in',
                'body' => 'Hi! I wanted to check in on progress and see if there are topics you would like to review in the next lesson.',
                'category' => MessageTemplate::CATEGORY_PROGRESS_CHECK_IN,
                'role_visibility' => [MessageTemplate::ROLE_ADMIN, MessageTemplate::ROLE_STAFF, MessageTemplate::ROLE_TEACHER],
            ],
        ];

        foreach ($templates as $template) {
            MessageTemplate::query()->updateOrCreate([
                'title' => $template['title'],
                'category' => $template['category'],
                'teacher_id' => null,
            ], [
                ...$template,
                'status' => MessageTemplate::STATUS_ACTIVE,
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ]);
        }
    }
}
