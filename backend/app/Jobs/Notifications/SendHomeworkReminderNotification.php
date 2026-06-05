<?php

namespace App\Jobs\Notifications;

use App\Models\Homework;
use App\Services\Notifications\SystemNotificationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendHomeworkReminderNotification implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    public function __construct(public readonly int $homeworkId) {}

    public function uniqueId(): string
    {
        return (string) $this->homeworkId;
    }

    public function handle(SystemNotificationService $notifications): void
    {
        $homework = Homework::query()
            ->with(['student:id,name,email,timezone', 'teacher:id,name,email,timezone'])
            ->find($this->homeworkId);

        if ($homework === null || $homework->student === null) {
            return;
        }

        try {
            $notifications->homeworkReminder(
                $homework->student,
                'Homework assigned: '.$homework->title,
                $homework->due_date
                    ? 'Your homework is due on '.$homework->due_date->format('M j, Y').'.'
                    : 'New homework has been assigned.',
                [
                    'homework_id' => $homework->id,
                    'lesson_id' => $homework->lesson_id,
                    'teacher_id' => $homework->teacher_id,
                ],
                [
                    'email' => true,
                    'queue_email' => true,
                    'sender_id' => $homework->teacher_id,
                    'source_type' => 'homework',
                    'source_id' => $homework->id,
                    'dedupe_key' => 'homework_assigned:'.$homework->id,
                ]
            );
        } catch (Throwable $exception) {
            Log::warning('Homework reminder notification delivery failed.', [
                'homework_id' => $homework->id,
                'student_id' => $homework->student_id,
                'teacher_id' => $homework->teacher_id,
                'failure_type' => $exception::class,
            ]);

            throw $exception;
        }
    }
}
