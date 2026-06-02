<?php

namespace App\Services\Announcements;

use App\Models\Announcement;
use App\Models\Notification;
use App\Services\Notifications\SystemNotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ScheduledAnnouncementPublisher
{
    private readonly SystemNotificationService $notificationService;

    public function __construct(
        private readonly AnnouncementRecipientResolver $recipientResolver,
        ?SystemNotificationService $notificationService = null
    ) {
        $this->notificationService = $notificationService ?? app(SystemNotificationService::class);
    }

    /**
     * Publish scheduled announcements that are due.
     *
     * Each claimed announcement has recipients synchronized, a portal
     * notification created or updated, and its status moved to published.
     * Publication failures are logged and counted without stopping the batch.
     *
     * @return array{published: int, failed: int, notifications: int, recipients: int}
     */
    public function publishDue(?CarbonImmutable $now = null, int $limit = 100): array
    {
        $now = ($now ?? CarbonImmutable::now('UTC'))->utc();

        $results = [
            'published' => 0,
            'failed' => 0,
            'notifications' => 0,
            'recipients' => 0,
        ];

        Announcement::query()
            ->where('status', Announcement::STATUS_SCHEDULED)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', $now)
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->each(function (int $announcementId) use ($now, &$results): void {
                try {
                    $published = $this->publishAnnouncement($announcementId, $now);

                    if ($published === null) {
                        return;
                    }

                    $results['published']++;
                    $results['notifications'] += $published['notification_created'] ? 1 : 0;
                    $results['recipients'] += $published['recipient_count'];
                } catch (Throwable $exception) {
                    $results['failed']++;

                    Log::error('Scheduled announcement publication failed.', [
                        'announcement_id' => $announcementId,
                        'failure_type' => $exception::class,
                    ]);
                }
            });

        Log::info('Scheduled announcement publication completed.', [
            'published' => $results['published'],
            'failed' => $results['failed'],
            'notifications_created' => $results['notifications'],
            'notification_recipients_upserted' => $results['recipients'],
            'processed_at' => $now->toIso8601String(),
        ]);

        return $results;
    }

    /**
     * @return array{notification_created: bool, recipient_count: int}|null
     */
    private function publishAnnouncement(int $announcementId, CarbonImmutable $now): ?array
    {
        return DB::transaction(function () use ($announcementId, $now): ?array {
            $announcement = Announcement::query()
                ->whereKey($announcementId)
                ->where('status', Announcement::STATUS_SCHEDULED)
                ->whereNotNull('scheduled_at')
                ->where('scheduled_at', '<=', $now)
                ->lockForUpdate()
                ->first();

            if ($announcement === null) {
                return null;
            }

            $this->recipientResolver->syncRecipients($announcement);

            $notification = $this->notificationFor($announcement, $now);

            $announcement->forceFill([
                'status' => Announcement::STATUS_PUBLISHED,
                'published_at' => $announcement->published_at ?? $now,
                'scheduled_at' => null,
            ])->save();

            return [
                'notification_created' => $notification->wasRecentlyCreated,
                'recipient_count' => $announcement->recipients()->count(),
            ];
        });
    }

    private function notificationFor(Announcement $announcement, CarbonImmutable $publishedAt): Notification
    {
        $recipients = $announcement->recipients()
            ->orderBy('id')
            ->get(['user_id', 'matched_targets']);

        return $this->notificationService->adminAnnouncement(
            $recipients->pluck('user_id')->all(),
            $announcement->title,
            $announcement->body,
            [
                'announcement_id' => $announcement->id,
            ],
            [
                'email' => true,
                'sender_id' => $announcement->author_id,
                'published_at' => $publishedAt,
                'source_type' => 'announcement',
                'source_id' => $announcement->id,
                'recipient_metadata' => $recipients
                    ->mapWithKeys(fn ($recipient) => [
                        $recipient->user_id => [
                            'matched_targets' => $recipient->matched_targets ?? [],
                        ],
                    ])
                    ->all(),
            ]
        );
    }
}
