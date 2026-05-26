<?php

use App\Services\Announcements\ScheduledAnnouncementPublisher;
use App\Services\Billing\InvoiceOverdueService;
use App\Services\Scheduling\ScheduleReminderService;
use App\Services\SubscriptionRenewalReminderService;
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

Artisan::command('invoices:mark-overdue', function (InvoiceOverdueService $invoices): void {
    $updated = $invoices->markOverdue();

    $this->info("Marked {$updated} invoice(s) overdue.");
})->purpose('Mark unpaid invoices overdue after their due date has passed');

Artisan::command('subscription-renewal-reminders:send {--limit=100}', function (SubscriptionRenewalReminderService $reminders): void {
    $updated = $reminders->refreshDueCandidates();
    $sent = $reminders->sendDue(limit: (int) $this->option('limit'));

    $this->info("Updated {$updated} renewal reminder candidate(s); sent {$sent} reminder(s).");
})->purpose('Queue and send due package renewal reminders');

Schedule::command('class-reminders:send')->everyMinute();
Schedule::command('announcements:publish-scheduled')->everyMinute();
Schedule::command('invoices:mark-overdue')->dailyAt('00:05')->timezone(config('app.timezone'));
Schedule::command('subscription-renewal-reminders:send')->dailyAt('09:00')->timezone(config('app.timezone'));
