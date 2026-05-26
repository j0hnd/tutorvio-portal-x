<?php

namespace App\Services\Announcements;

use App\Models\Announcement;
use App\Models\Notification;
use App\Models\NotificationRecipient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ScheduledAnnouncementPublisher
{
    public function __construct(private readonly AnnouncementRecipientResolver $recipientResolver) {}

    /**
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
                        'error' => $exception->getMessage(),
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
            $this->syncNotificationRecipients($notification, $announcement, $now);

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
        $notification = Notification::query()
            ->where('type', Notification::TYPE_ADMIN_ANNOUNCEMENT)
            ->where('metadata->announcement_id', $announcement->id)
            ->first();

        if ($notification !== null) {
            $notification->forceFill([
                'title' => $announcement->title,
                'body' => $announcement->body,
                'sender_id' => $announcement->author_id,
                'published_at' => $notification->published_at ?? $publishedAt,
                'scheduled_at' => null,
                'is_archived' => false,
            ])->save();

            return $notification;
        }

        return Notification::create([
            'title' => $announcement->title,
            'body' => $announcement->body,
            'type' => Notification::TYPE_ADMIN_ANNOUNCEMENT,
            'sender_id' => $announcement->author_id,
            'scheduled_at' => null,
            'published_at' => $publishedAt,
            'metadata' => [
                'announcement_id' => $announcement->id,
            ],
        ]);
    }

    private function syncNotificationRecipients(Notification $notification, Announcement $announcement, CarbonImmutable $publishedAt): void
    {
        $now = now();

        $announcement->recipients()
            ->orderBy('id')
            ->chunk(500, function (Collection $recipients) use ($notification, $announcement, $publishedAt, $now): void {
                NotificationRecipient::upsert(
                    $recipients->map(fn ($recipient) => [
                        'notification_id' => $notification->id,
                        'user_id' => $recipient->user_id,
                        'channel' => NotificationRecipient::CHANNEL_IN_PORTAL,
                        'delivery_status' => NotificationRecipient::STATUS_DELIVERED,
                        'sent_at' => $publishedAt,
                        'delivered_at' => $publishedAt,
                        'metadata' => json_encode([
                            'announcement_id' => $announcement->id,
                            'matched_targets' => $recipient->matched_targets ?? [],
                        ]),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all(),
                    ['notification_id', 'user_id', 'channel'],
                    ['delivery_status', 'sent_at', 'delivered_at', 'metadata', 'updated_at']
                );
            });
    }
}
