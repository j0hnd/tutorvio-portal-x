<?php

use App\Services\Scheduling\ScheduleReminderService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('class-reminders:send {--lookahead-hours=48}', function (ScheduleReminderService $reminders): void {
    $queued = $reminders->queueUpcoming((int) $this->option('lookahead-hours'));
    $sent = $reminders->sendDue();

    $this->info("Queued {$queued} reminder(s); sent {$sent} reminder(s).");
})->purpose('Queue and send due class reminders');

Schedule::command('class-reminders:send')->everyMinute();
