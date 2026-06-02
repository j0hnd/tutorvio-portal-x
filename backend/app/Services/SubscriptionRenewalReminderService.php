<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Subscription;
use App\Services\Notifications\SystemNotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class SubscriptionRenewalReminderService
{
    private const ENDING_LOOKAHEAD_DAYS = 7;

    private const LOW_REMAINING_LESSON_THRESHOLD = 2;

    public function __construct(private readonly SystemNotificationService $notifications) {}

    /**
     * Calculate the renewal reminder state for a subscription.
     *
     * The method evaluates eligibility and reminder signals without persisting
     * changes, returning due timing, window key, reasons, and send readiness.
     *
     * @return array<string, mixed>
     */
    public function reminderState(Subscription $subscription, ?CarbonImmutable $now = null): array
    {
        $now = ($now ?? CarbonImmutable::now('UTC'))->utc();

        if (! $this->isRenewalEligible($subscription)) {
            return [
                'status' => Subscription::RENEWAL_REMINDER_STATUS_NOT_ELIGIBLE,
                'due_at' => null,
                'window_key' => null,
                'reasons' => [],
                'should_send' => false,
            ];
        }

        $signals = $this->signals($subscription, $now);

        if ($signals === []) {
            return [
                'status' => Subscription::RENEWAL_REMINDER_STATUS_NONE,
                'due_at' => null,
                'window_key' => null,
                'reasons' => [],
                'should_send' => false,
            ];
        }

        $dueAt = collect($signals)
            ->pluck('due_at')
            ->filter()
            ->sort()
            ->first();
        $dueAt = $dueAt instanceof CarbonImmutable ? $dueAt : $now;
        $windowKey = $this->windowKey($subscription, $signals);
        $alreadySent = $subscription->renewal_reminder_window_key === $windowKey
            && $subscription->renewal_reminder_last_sent_at !== null;
        $isDue = $dueAt->lessThanOrEqualTo($now);

        return [
            'status' => $alreadySent
                ? Subscription::RENEWAL_REMINDER_STATUS_SENT
                : ($isDue ? Subscription::RENEWAL_REMINDER_STATUS_PENDING : Subscription::RENEWAL_REMINDER_STATUS_NONE),
            'due_at' => $dueAt,
            'window_key' => $windowKey,
            'reasons' => array_values(array_unique(array_column($signals, 'reason'))),
            'should_send' => $isDue && ! $alreadySent,
        ];
    }

    /**
     * Refresh the stored renewal reminder state for a subscription.
     *
     * The subscription's due timestamp, status, and reminder window key are
     * updated from the current calculated state.
     */
    public function refreshReminderState(Subscription $subscription, ?CarbonImmutable $now = null): Subscription
    {
        $state = $this->reminderState($subscription, $now);

        $subscription->forceFill([
            'renewal_reminder_due_at' => $state['due_at'],
            'renewal_reminder_status' => $state['status'],
            'renewal_reminder_window_key' => $state['window_key'] ?? $subscription->renewal_reminder_window_key,
        ])->save();

        return $subscription->refresh();
    }

    /**
     * Refresh renewal reminder state for subscriptions likely to be due.
     *
     * Matching subscriptions are processed in chunks, and the return value is
     * the number whose due timestamp, status, or window key changed.
     */
    public function refreshDueCandidates(?CarbonImmutable $now = null): int
    {
        $now = ($now ?? CarbonImmutable::now('UTC'))->utc();
        $updated = 0;

        Subscription::query()
            ->where('renewal_eligible', true)
            ->whereIn('status', [
                Subscription::STATUS_ACTIVE,
                Subscription::STATUS_INACTIVE,
                Subscription::STATUS_EXPIRED,
            ])
            ->whereDoesntHave('renewals')
            ->where(function ($query) use ($now) {
                $query->where('remaining_lesson_count', '<=', self::LOW_REMAINING_LESSON_THRESHOLD)
                    ->orWhereIn('status', [Subscription::STATUS_INACTIVE, Subscription::STATUS_EXPIRED])
                    ->orWhere('ends_at', '<=', $now->addDays(self::ENDING_LOOKAHEAD_DAYS));
            })
            ->orderBy('id')
            ->chunkById(100, function (Collection $subscriptions) use ($now, &$updated): void {
                $subscriptions->each(function (Subscription $subscription) use ($now, &$updated): void {
                    $before = [
                        $subscription->renewal_reminder_due_at?->toJSON(),
                        $subscription->renewal_reminder_status,
                        $subscription->renewal_reminder_window_key,
                    ];

                    $this->refreshReminderState($subscription, $now);

                    $after = [
                        $subscription->renewal_reminder_due_at?->toJSON(),
                        $subscription->renewal_reminder_status,
                        $subscription->renewal_reminder_window_key,
                    ];

                    if ($before !== $after) {
                        $updated++;
                    }
                });
            });

        return $updated;
    }

    /**
     * Send due subscription renewal reminders.
     *
     * Pending subscriptions are claimed, portal reminders are created for the
     * student, and last-sent reminder fields are updated to prevent duplicate
     * sends within the same reminder window.
     */
    public function sendDue(?CarbonImmutable $now = null, int $limit = 100): int
    {
        $now = ($now ?? CarbonImmutable::now('UTC'))->utc();
        $sent = 0;

        Subscription::query()
            ->with('student:id,name,email,status')
            ->where('renewal_eligible', true)
            ->where('renewal_reminder_status', Subscription::RENEWAL_REMINDER_STATUS_PENDING)
            ->where('renewal_reminder_due_at', '<=', $now)
            ->orderBy('renewal_reminder_due_at')
            ->limit($limit)
            ->get()
            ->each(function (Subscription $subscription) use ($now, &$sent): void {
                $state = $this->reminderState($subscription, $now);

                if (! $state['should_send']) {
                    $this->refreshReminderState($subscription, $now);

                    return;
                }

                $claimed = Subscription::query()
                    ->whereKey($subscription->id)
                    ->where('renewal_reminder_status', Subscription::RENEWAL_REMINDER_STATUS_PENDING)
                    ->where(function ($query) use ($state) {
                        $query->whereNull('renewal_reminder_last_sent_at')
                            ->orWhere('renewal_reminder_window_key', '!=', $state['window_key']);
                    })
                    ->update(['renewal_reminder_status' => Subscription::RENEWAL_REMINDER_STATUS_SENT]);

                if ($claimed === 0) {
                    return;
                }

                $this->createPortalReminder($subscription, $state, $now);

                $subscription->forceFill([
                    'renewal_reminder_last_sent_at' => $now,
                    'renewal_reminder_status' => Subscription::RENEWAL_REMINDER_STATUS_SENT,
                    'renewal_reminder_window_key' => $state['window_key'],
                ])->save();

                $sent++;
            });

        return $sent;
    }

    private function isRenewalEligible(Subscription $subscription): bool
    {
        return $subscription->renewal_eligible
            && $subscription->status !== Subscription::STATUS_CANCELLED
            && ! $subscription->renewals()->exists();
    }

    /**
     * @return array<int, array{reason: string, due_at: CarbonImmutable, key: string}>
     */
    private function signals(Subscription $subscription, CarbonImmutable $now): array
    {
        $signals = [];

        if ($subscription->ends_at !== null) {
            $endsAt = CarbonImmutable::parse($subscription->ends_at)->utc();

            if ($endsAt->lessThanOrEqualTo($now->addDays(self::ENDING_LOOKAHEAD_DAYS))) {
                $signals[] = [
                    'reason' => 'ending_soon',
                    'due_at' => $endsAt->subDays(self::ENDING_LOOKAHEAD_DAYS),
                    'key' => 'ending:'.$endsAt->toDateString(),
                ];
            }
        }

        if ($subscription->remaining_lesson_count <= self::LOW_REMAINING_LESSON_THRESHOLD) {
            $signals[] = [
                'reason' => 'low_balance',
                'due_at' => $now,
                'key' => 'low_balance:'.$subscription->remaining_lesson_count,
            ];
        }

        if (in_array($subscription->status, [Subscription::STATUS_INACTIVE, Subscription::STATUS_EXPIRED], true)) {
            $signals[] = [
                'reason' => 'inactive_or_expired',
                'due_at' => $now,
                'key' => 'status:'.$subscription->status,
            ];
        }

        return $signals;
    }

    /**
     * @param  array<int, array{reason: string, due_at: CarbonImmutable, key: string}>  $signals
     */
    private function windowKey(Subscription $subscription, array $signals): string
    {
        return 'subscription-renewal:'.$subscription->id.':'.implode('|', array_column($signals, 'key'));
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function createPortalReminder(Subscription $subscription, array $state, CarbonImmutable $now): Notification
    {
        return $this->notifications->create(
            Notification::TYPE_RENEWAL_REMINDER,
            $subscription->student,
            'Package renewal reminder',
            $this->message($subscription, $state['reasons']),
            [
                'subscription_id' => $subscription->id,
                'plan_name' => $subscription->plan_name,
                'reasons' => $state['reasons'],
                'window_key' => $state['window_key'],
            ],
            [
                'published_at' => $now,
                'dedupe_key' => $state['window_key'],
                'email' => (bool) config('subscriptions.renewal_reminders.email_enabled', false),
            ]
        );
    }

    /**
     * @param  array<int, string>  $reasons
     */
    private function message(Subscription $subscription, array $reasons): string
    {
        if (in_array('low_balance', $reasons, true)) {
            return "Your {$subscription->plan_name} package has {$subscription->remaining_lesson_count} lesson(s) remaining.";
        }

        if (in_array('inactive_or_expired', $reasons, true)) {
            return "Your {$subscription->plan_name} package is {$subscription->status}.";
        }

        return "Your {$subscription->plan_name} package is ending soon.";
    }
}
