<?php

namespace App\Services\Notifications;

use App\Jobs\Notifications\SendSystemNotificationEmail;
use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Models\User;
use App\Notifications\SystemNotificationEmail;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SystemNotificationService
{
    /**
     * Create a class reminder notification for portal and optional email delivery.
     *
     * Recipients may be user models, user IDs, or iterables of either. Options
     * are forwarded to the generic notification creator for scheduling,
     * deduplication, source metadata, and email dispatch behavior.
     *
     * @param  User|int|iterable<int, User|int>  $recipients
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $options
     */
    public function classReminder(User|int|iterable $recipients, string $title, ?string $body = null, array $metadata = [], array $options = []): Notification
    {
        return $this->create(Notification::TYPE_CLASS_REMINDER, $recipients, $title, $body, $metadata, $options);
    }

    /**
     * Create a reschedule alert notification.
     *
     * Recipients are synchronized to portal notification rows, and email rows
     * are sent immediately or queued when the options request it.
     *
     * @param  User|int|iterable<int, User|int>  $recipients
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $options
     */
    public function rescheduleAlert(User|int|iterable $recipients, string $title, ?string $body = null, array $metadata = [], array $options = []): Notification
    {
        return $this->create(Notification::TYPE_RESCHEDULE_ALERT, $recipients, $title, $body, $metadata, $options);
    }

    /**
     * Create a homework reminder notification.
     *
     * Recipients are synchronized to portal notification rows, and email rows
     * are sent immediately or queued when enabled through options.
     *
     * @param  User|int|iterable<int, User|int>  $recipients
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $options
     */
    public function homeworkReminder(User|int|iterable $recipients, string $title, ?string $body = null, array $metadata = [], array $options = []): Notification
    {
        return $this->create(Notification::TYPE_HOMEWORK_REMINDER, $recipients, $title, $body, $metadata, $options);
    }

    /**
     * Create an administrative announcement notification.
     *
     * The method delegates to the generic notification creator, preserving
     * source metadata and recipient-specific matched-target metadata when
     * supplied.
     *
     * @param  User|int|iterable<int, User|int>  $recipients
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $options
     */
    public function adminAnnouncement(User|int|iterable $recipients, string $title, ?string $body = null, array $metadata = [], array $options = []): Notification
    {
        return $this->create(Notification::TYPE_ADMIN_ANNOUNCEMENT, $recipients, $title, $body, $metadata, $options);
    }

    /**
     * Create a general system notice notification.
     *
     * Recipients are synchronized to portal notification rows, with optional
     * scheduling, deduplication, and immediate or queued email delivery
     * controlled by options.
     *
     * @param  User|int|iterable<int, User|int>  $recipients
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $options
     */
    public function systemNotice(User|int|iterable $recipients, string $title, ?string $body = null, array $metadata = [], array $options = []): Notification
    {
        return $this->create(Notification::TYPE_SYSTEM, $recipients, $title, $body, $metadata, $options);
    }

    /**
     * Create or update a portal notification and synchronize its recipients.
     *
     * This shared system-level handler runs when application services need to
     * publish a portal notification. It normalizes recipient inputs, applies
     * source and deduplication metadata, writes notification and in-portal
     * recipient rows in a transaction, and creates email delivery rows when
     * requested. Email delivery is queued when `queue_email` is true; otherwise
     * it is attempted synchronously. Delivery rows are claimed before sending,
     * then marked sent or failed, with failures logged as warnings. It is safe
     * to retry when callers provide `source_type` plus `source_id` or a
     * `dedupe_key`; otherwise each call creates a new notification.
     *
     * @param  User|int|iterable<int, User|int>  $recipients
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $options
     */
    public function create(
        string $type,
        User|int|iterable $recipients,
        string $title,
        ?string $body = null,
        array $metadata = [],
        array $options = []
    ): Notification {
        $recipientUsers = $this->recipientUsers($recipients);
        $publishedAt = $this->timestamp($options['published_at'] ?? CarbonImmutable::now('UTC'));
        $scheduledAt = isset($options['scheduled_at']) ? $this->timestamp($options['scheduled_at']) : null;
        $metadata = $this->metadataWithSource($metadata, $options);

        $notification = DB::transaction(function () use ($type, $recipientUsers, $title, $body, $metadata, $options, $publishedAt, $scheduledAt): Notification {
            $notification = $this->findDuplicate($type, $metadata);

            if ($notification !== null) {
                $updates = [
                    'title' => $title,
                    'body' => $body,
                    'published_at' => $notification->published_at ?? $publishedAt,
                    'is_archived' => false,
                    'metadata' => $metadata,
                ];

                if (array_key_exists('sender_id', $options)) {
                    $updates['sender_id'] = $options['sender_id'];
                }

                if (array_key_exists('scheduled_at', $options)) {
                    $updates['scheduled_at'] = $scheduledAt;
                }

                $notification->forceFill($updates)->save();
            } else {
                $notification = Notification::create([
                    'title' => $title,
                    'body' => $body,
                    'type' => $type,
                    'sender_id' => $options['sender_id'] ?? null,
                    'scheduled_at' => $scheduledAt,
                    'published_at' => $publishedAt,
                    'metadata' => $metadata,
                ]);
            }

            $this->syncPortalRecipients($notification, $recipientUsers, $metadata, $publishedAt, $options['recipient_metadata'] ?? []);

            return $notification;
        });

        if ($options['email'] ?? false) {
            $this->dispatchEmails($notification, $recipientUsers, $metadata, $options);
        }

        return $notification->refresh();
    }

    /**
     * Create email recipient rows and dispatch or send each eligible delivery.
     *
     * Already sending or delivered rows are skipped so repeated calls do not
     * enqueue duplicate email work for the same notification recipient.
     *
     * @param  Collection<int, User>  $recipients
     * @param  array<string, mixed>  $metadata
     */
    private function syncPortalRecipients(Notification $notification, Collection $recipients, array $metadata, CarbonImmutable $publishedAt, array $recipientMetadata): void
    {
        if ($recipients->isEmpty()) {
            return;
        }

        $now = now();

        NotificationRecipient::upsert(
            $recipients->map(fn (User $recipient) => [
                'notification_id' => $notification->id,
                'user_id' => $recipient->id,
                'channel' => NotificationRecipient::CHANNEL_IN_PORTAL,
                'delivery_status' => NotificationRecipient::STATUS_DELIVERED,
                'sent_at' => $publishedAt,
                'delivered_at' => $publishedAt,
                'metadata' => json_encode($this->metadataForRecipient($metadata, $recipientMetadata, $recipient->id)),
                'created_at' => $now,
                'updated_at' => $now,
            ])->all(),
            ['notification_id', 'user_id', 'channel'],
            ['delivery_status', 'sent_at', 'delivered_at', 'metadata', 'updated_at']
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<int|string, array<string, mixed>>  $recipientMetadata
     * @return array<string, mixed>
     */
    private function metadataForRecipient(array $metadata, array $recipientMetadata, int $userId): array
    {
        return [
            ...$metadata,
            ...($recipientMetadata[$userId] ?? []),
        ];
    }

    /**
     * @param  Collection<int, User>  $recipients
     * @param  array<string, mixed>  $metadata
     */
    private function dispatchEmails(Notification $notification, Collection $recipients, array $metadata, array $options): void
    {
        $recipients
            ->filter(fn (User $recipient) => (bool) $recipient->email)
            ->each(function (User $recipient) use ($notification, $metadata, $options): void {
                $delivery = NotificationRecipient::firstOrCreate([
                    'notification_id' => $notification->id,
                    'user_id' => $recipient->id,
                    'channel' => NotificationRecipient::CHANNEL_EMAIL,
                ], [
                    'delivery_status' => NotificationRecipient::STATUS_PENDING,
                    'metadata' => $metadata,
                ]);

                if (in_array($delivery->delivery_status, [
                    NotificationRecipient::STATUS_SENDING,
                    NotificationRecipient::STATUS_SENT,
                    NotificationRecipient::STATUS_DELIVERED,
                ], true)) {
                    return;
                }

                if ($options['queue_email'] ?? false) {
                    SendSystemNotificationEmail::dispatch($delivery->id);

                    return;
                }

                $this->sendDelivery($delivery->id);
            });
    }

    /**
     * Send an email delivery requested by a queued notification job.
     */
    public function sendQueuedEmail(int $deliveryId): void
    {
        $this->sendDelivery($deliveryId);
    }

    /**
     * Claim one email delivery row and attempt notification email delivery.
     */
    private function sendDelivery(int $deliveryId): void
    {
        $delivery = NotificationRecipient::query()
            ->with(['notification', 'user'])
            ->find($deliveryId);

        if ($delivery === null || $delivery->notification === null || $delivery->user === null) {
            return;
        }

        if ($delivery->channel !== NotificationRecipient::CHANNEL_EMAIL) {
            return;
        }

        if (in_array($delivery->delivery_status, [NotificationRecipient::STATUS_SENT, NotificationRecipient::STATUS_DELIVERED], true)) {
            return;
        }

        $claimed = NotificationRecipient::query()
            ->whereKey($delivery->id)
            ->whereIn('delivery_status', [NotificationRecipient::STATUS_PENDING, NotificationRecipient::STATUS_FAILED])
            ->update(['delivery_status' => NotificationRecipient::STATUS_SENDING]);

        if ($claimed === 0) {
            return;
        }

        $delivery->refresh();
        $metadata = $delivery->metadata ?? [];

        try {
            $delivery->user->notify(new SystemNotificationEmail($delivery->notification));

            $delivery->forceFill([
                'delivery_status' => NotificationRecipient::STATUS_SENT,
                'sent_at' => now(),
                'metadata' => $metadata,
            ])->save();
        } catch (Throwable $exception) {
            $delivery->forceFill([
                'delivery_status' => NotificationRecipient::STATUS_FAILED,
                'metadata' => [
                    ...$metadata,
                    'failure_type' => $exception::class,
                ],
            ])->save();

            Log::warning('Notification email delivery failed.', [
                'notification_id' => $delivery->notification_id,
                'notification_type' => $delivery->notification->type,
                'user_id' => $delivery->user_id,
                'delivery_id' => $delivery->id,
                'failure_type' => $exception::class,
            ]);
        }
    }

    private function findDuplicate(string $type, array $metadata): ?Notification
    {
        if (isset($metadata['source_type'], $metadata['source_id'])) {
            return Notification::query()
                ->where('type', $type)
                ->where('metadata->source_type', $metadata['source_type'])
                ->where('metadata->source_id', $metadata['source_id'])
                ->first();
        }

        if (isset($metadata['dedupe_key'])) {
            return Notification::query()
                ->where('type', $type)
                ->where('metadata->dedupe_key', $metadata['dedupe_key'])
                ->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function metadataWithSource(array $metadata, array $options): array
    {
        if (isset($options['source_type'])) {
            $metadata['source_type'] = $options['source_type'];
        }

        if (isset($options['source_id'])) {
            $metadata['source_id'] = $options['source_id'];
        }

        if (isset($options['dedupe_key'])) {
            $metadata['dedupe_key'] = $options['dedupe_key'];
        }

        return $metadata;
    }

    /**
     * @param  User|int|iterable<int, User|int>  $recipients
     * @return Collection<int, User>
     */
    private function recipientUsers(User|int|iterable $recipients): Collection
    {
        if ($recipients instanceof User || is_int($recipients)) {
            $recipients = [$recipients];
        }

        $users = collect($recipients)->filter(fn ($recipient) => $recipient instanceof User);
        $ids = collect($recipients)
            ->filter(fn ($recipient) => is_int($recipient))
            ->values();

        if ($ids->isNotEmpty()) {
            $users = $users->merge(User::query()->whereKey($ids)->get());
        }

        return $users
            ->unique('id')
            ->values();
    }

    private function timestamp(mixed $value): CarbonImmutable
    {
        return $value instanceof CarbonImmutable
            ? $value->utc()
            : CarbonImmutable::parse($value)->utc();
    }
}
