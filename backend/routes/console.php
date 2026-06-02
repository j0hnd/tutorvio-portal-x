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

/**
 * Queue upcoming class reminders and send reminders that are due.
 *
 * This command runs every minute from the scheduler. It creates reminder rows,
 * sends due reminder emails, creates companion portal notifications, updates
 * reminder and notification delivery statuses, and logs delivery failures in
 * the reminder service. It is safe to retry because queued reminders are
 * deduplicated and only pending reminders are claimed for sending.
 */
Artisan::command('class-reminders:send {--lookahead-hours=48}', function (ScheduleReminderService $reminders): void {
    $queued = $reminders->queueUpcoming((int) $this->option('lookahead-hours'));
    $sent = $reminders->sendDue();

    $this->info("Queued {$queued} reminder(s); sent {$sent} reminder(s).");
})->purpose('Queue and send due class reminders');

/**
 * Publish due scheduled announcements.
 *
 * This command runs every minute from the scheduler. It syncs announcement
 * recipients, creates or updates portal notifications, requests email delivery,
 * marks announcements published, and logs batch completion plus per-announcement
 * publication failures. It is safe to retry because already-published
 * announcements are skipped and notification creation is deduplicated by source
 * metadata.
 */
Artisan::command('announcements:publish-scheduled {--limit=100}', function (ScheduledAnnouncementPublisher $publisher): void {
    $results = $publisher->publishDue(limit: (int) $this->option('limit'));

    $this->info(
        "Published {$results['published']} announcement(s); "
        ."created {$results['notifications']} notification(s); "
        ."upserted {$results['recipients']} recipient notification(s); "
        ."failed {$results['failed']} announcement(s)."
    );
})->purpose('Publish due scheduled announcements and create portal notifications');

/**
 * Mark unpaid past-due invoices as overdue.
 *
 * This command runs daily from the scheduler. It updates invoice statuses in
 * bulk using the application timezone. It does not send email, create portal
 * notifications, or write logs, and it is safe to retry because invoices already
 * moved out of the unpaid status no longer match.
 */
Artisan::command('invoices:mark-overdue', function (InvoiceOverdueService $invoices): void {
    $updated = $invoices->markOverdue();

    $this->info("Marked {$updated} invoice(s) overdue.");
})->purpose('Mark unpaid invoices overdue after their due date has passed');

/**
 * Refresh package renewal reminder candidates and send due reminders.
 *
 * This command runs daily from the scheduler. It recalculates subscription
 * reminder state, creates portal renewal reminders, optionally sends email
 * through the notification service, and updates last-sent reminder fields. It
 * does not write logs directly, and it is safe to retry because reminder window
 * keys prevent duplicate sends in the same window.
 */
Artisan::command('subscription-renewal-reminders:send {--limit=100}', function (SubscriptionRenewalReminderService $reminders): void {
    $updated = $reminders->refreshDueCandidates();
    $sent = $reminders->sendDue(limit: (int) $this->option('limit'));

    $this->info("Updated {$updated} renewal reminder candidate(s); sent {$sent} reminder(s).");
})->purpose('Queue and send due package renewal reminders');

Schedule::command('class-reminders:send')->everyMinute();
Schedule::command('announcements:publish-scheduled')->everyMinute();
Schedule::command('invoices:mark-overdue')->dailyAt('00:05')->timezone(config('app.timezone'));
Schedule::command('subscription-renewal-reminders:send')->dailyAt('09:00')->timezone(config('app.timezone'));
