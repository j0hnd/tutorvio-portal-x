<?php

namespace App\Jobs\Scheduling;

use App\Services\Scheduling\ScheduleReminderService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendClassReminderNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $scheduleReminderId,
        public readonly ?string $dueAt = null,
    ) {}

    public function handle(ScheduleReminderService $reminders): void
    {
        $reminders->sendReminder(
            $this->scheduleReminderId,
            $this->dueAt ? CarbonImmutable::parse($this->dueAt, 'UTC') : null
        );
    }
}
