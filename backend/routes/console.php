<?php

use App\Services\Announcements\ScheduledAnnouncementPublisher;
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

Artisan::command('announcements:publish-scheduled {--limit=100}', function (ScheduledAnnouncementPublisher $publisher): void {
    $results = $publisher->publishDue(limit: (int) $this->option('limit'));

    $this->info(
        "Published {$results['published']} announcement(s); "
        ."created {$results['notifications']} notification(s); "
        ."upserted {$results['recipients']} recipient notification(s); "
        ."failed {$results['failed']} announcement(s)."
    );
})->purpose('Publish due scheduled announcements and create portal notifications');

Schedule::command('class-reminders:send')->everyMinute();
Schedule::command('announcements:publish-scheduled')->everyMinute();
