<?php

namespace App\Services;

use App\Enums\AuditActionType;
use App\Enums\AuditModule;
use App\Models\LessonRecord;
use App\Models\Subscription;
use App\Models\SubscriptionHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionLessonBalanceService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    /**
     * Consume one lesson from a student's active subscription for a completed lesson.
     *
     * The lesson record is returned unchanged when it is not completed, already
     * consumed, or no active consumable subscription exists. When consumed, the
     * subscription balance, lesson record markers, subscription history, and
     * audit log are updated in a transaction.
     *
     * @throws ValidationException
     */
    public function consumeForCompletedLesson(LessonRecord $lessonRecord, User $actor): LessonRecord
    {
        if (! $lessonRecord->is_completed || $lessonRecord->lesson_status !== LessonRecord::STATUS_COMPLETED) {
            return $lessonRecord;
        }

        if ($lessonRecord->lesson_balance_consumed_subscription_id !== null) {
            return $lessonRecord;
        }

        return DB::transaction(function () use ($lessonRecord, $actor): LessonRecord {
            $lessonRecord = LessonRecord::query()
                ->whereKey($lessonRecord->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lessonRecord->lesson_balance_consumed_subscription_id !== null) {
                return $lessonRecord;
            }

            $subscription = $this->activeConsumableSubscriptionForStudent((int) $lessonRecord->student_id);

            if ($subscription === null) {
                return $lessonRecord;
            }

            $this->assertCanConsume($subscription);

            $previous = $subscription->only($this->trackedFields());

            $subscription->consumed_lesson_count++;
            $subscription->remaining_lesson_count = max(0, $subscription->total_lesson_count - $subscription->consumed_lesson_count);
            $subscription->updated_by = $actor->id;
            $subscription->save();
            $subscription->refresh();

            $lessonRecord->forceFill([
                'lesson_balance_consumed_subscription_id' => $subscription->id,
                'lesson_balance_consumed_at' => now(),
                'updated_by' => $actor->id,
            ])->save();

            $this->recordHistory(
                $subscription,
                SubscriptionHistory::EVENT_LESSONS_CONSUMED,
                $previous,
                $subscription->only($this->trackedFields()),
                $actor->id,
                'Lesson balance consumed for lesson record #'.$lessonRecord->id,
                ['lesson_record_id' => $lessonRecord->id]
            );

            return $lessonRecord->refresh();
        });
    }

    /**
     * Manually adjust a subscription lesson balance.
     *
     * The payload is expected to be validated upstream and must preserve the
     * total, consumed, and remaining balance invariant. The update writes
     * subscription history and an audit log when values change.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    public function manuallyAdjust(Subscription $subscription, array $payload, User $actor): Subscription
    {
        return DB::transaction(function () use ($subscription, $payload, $actor): Subscription {
            $subscription = Subscription::query()
                ->whereKey($subscription->id)
                ->lockForUpdate()
                ->firstOrFail();
            $previous = $subscription->only($this->trackedFields());

            $total = array_key_exists('total_lesson_count', $payload)
                ? (int) $payload['total_lesson_count']
                : (int) $subscription->total_lesson_count;
            $consumed = array_key_exists('consumed_lesson_count', $payload)
                ? (int) $payload['consumed_lesson_count']
                : (int) $subscription->consumed_lesson_count;
            $remaining = array_key_exists('remaining_lesson_count', $payload)
                ? (int) $payload['remaining_lesson_count']
                : $total - $consumed;

            if ($consumed > $total || $remaining < 0 || $remaining !== $total - $consumed) {
                throw ValidationException::withMessages([
                    'lesson_balance' => 'Lesson balance must satisfy consumed <= total and remaining = total - consumed.',
                ]);
            }

            $subscription->fill([
                'total_lesson_count' => $total,
                'consumed_lesson_count' => $consumed,
                'remaining_lesson_count' => $remaining,
                'updated_by' => $actor->id,
            ])->save();
            $subscription->refresh();
            $current = $subscription->only($this->trackedFields());

            $this->recordHistory(
                $subscription,
                SubscriptionHistory::EVENT_MANUAL_BALANCE_ADJUSTED,
                $previous,
                $current,
                $actor->id,
                (string) $payload['notes']
            );

            $changedFields = $this->changedFields($previous, $current);
            $adjustmentAmount = (int) ($current['remaining_lesson_count'] ?? 0) - (int) ($previous['remaining_lesson_count'] ?? 0);

            $this->auditLogService->record(
                actorUserId: $actor->id,
                actionType: AuditActionType::LESSON_BALANCE_ADJUSTED,
                module: AuditModule::PACKAGES,
                targetEntityType: 'subscription',
                targetEntityId: $subscription->id,
                metadata: [
                    'subscription_id' => $subscription->id,
                    'package_id' => $subscription->id,
                    'affected_user_id' => $subscription->user_id,
                    'changed_fields' => array_fill_keys($changedFields, true),
                    'adjustment_amount' => $adjustmentAmount,
                    'old_status' => $previous['status'] ?? null,
                    'new_status' => $current['status'] ?? null,
                ],
            );

            return $subscription;
        });
    }

    private function activeConsumableSubscriptionForStudent(int $studentId): ?Subscription
    {
        return Subscription::query()
            ->where('user_id', $studentId)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->where('is_frozen', false)
            ->whereColumn('consumed_lesson_count', '<', 'total_lesson_count')
            ->where('remaining_lesson_count', '>', 0)
            ->where(function ($query) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            })
            ->orderBy('starts_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();
    }

    /**
     * Validate whether lesson balance can be consumed from a subscription.
     *
     * Consumption is denied when the package is not active, is frozen, has
     * already consumed all lessons, or has no remaining lessons. Role and
     * Spatie permission authorization is handled before this domain guard.
     */
    private function assertCanConsume(Subscription $subscription): void
    {
        if ($subscription->status !== Subscription::STATUS_ACTIVE || $subscription->is_frozen) {
            throw ValidationException::withMessages([
                'subscription_id' => 'Lesson balance can only be consumed from an active, unfrozen package.',
            ]);
        }

        if ($subscription->consumed_lesson_count >= $subscription->total_lesson_count || $subscription->remaining_lesson_count <= 0) {
            throw ValidationException::withMessages([
                'lesson_balance' => 'The selected package has no remaining lessons.',
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function trackedFields(): array
    {
        return [
            'plan_name',
            'package_type',
            'total_lesson_count',
            'consumed_lesson_count',
            'remaining_lesson_count',
            'status',
            'is_frozen',
            'payment_status',
            'invoice_id',
            'invoice_reference',
            'internal_notes',
            'renewed_from_subscription_id',
            'starts_at',
            'ends_at',
        ];
    }

    /**
     * @param  array<string, mixed>  $previousValues
     * @param  array<string, mixed>  $newValues
     * @param  array<string, mixed>  $metadata
     */
    private function recordHistory(
        Subscription $subscription,
        string $eventType,
        array $previousValues,
        array $newValues,
        int $createdBy,
        ?string $notes = null,
        array $metadata = []
    ): void {
        SubscriptionHistory::create([
            'subscription_id' => $subscription->id,
            'student_id' => $subscription->user_id,
            'event_type' => $eventType,
            'plan_name' => $subscription->plan_name,
            'package_type' => $subscription->package_type,
            'total_lesson_count' => $subscription->total_lesson_count,
            'consumed_lesson_count' => $subscription->consumed_lesson_count,
            'remaining_lesson_count' => $subscription->remaining_lesson_count,
            'status' => $subscription->status,
            'is_frozen' => $subscription->is_frozen,
            'payment_status' => $subscription->payment_status,
            'starts_at' => $subscription->starts_at,
            'ends_at' => $subscription->ends_at,
            'previous_values' => $previousValues,
            'new_values' => [...$newValues, ...$metadata],
            'notes' => $notes,
            'effective_at' => now(),
            'created_by' => $createdBy,
        ]);
    }

    /**
     * @param  array<string, mixed>  $previous
     * @param  array<string, mixed>  $current
     * @return array<int, string>
     */
    private function changedFields(array $previous, array $current): array
    {
        $changed = [];

        foreach ($current as $field => $value) {
            if (! array_key_exists($field, $previous) || $previous[$field] != $value) {
                $changed[] = $field;
            }
        }

        return array_values(array_unique($changed));
    }
}
