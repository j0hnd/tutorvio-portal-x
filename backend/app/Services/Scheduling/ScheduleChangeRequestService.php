<?php

namespace App\Services\Scheduling;

use App\Models\Lesson;
use App\Models\ScheduleChangeRequest;
use App\Models\Scheduling\ClassSchedule;
use App\Models\User;
use App\Services\PortalSettings\PortalSettingsService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScheduleChangeRequestService
{
    private const APPROVAL_SETTING = 'scheduling.schedule_change_requires_approval';

    public function __construct(
        private readonly ClassScheduleService $classScheduleService,
        private readonly PortalSettingsService $portalSettings,
    ) {}

    /**
     * Request a schedule change for a lesson or class schedule.
     *
     * The payload is expected to identify exactly one target and provide the
     * requested range. The method stores a pending request or immediately
     * approves it when portal settings disable approval.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    public function request(array $payload, User $actor): ScheduleChangeRequest
    {
        $target = $this->targetFromPayload($payload);
        $this->assertCanRequestForTarget($actor, $target);

        $timezone = $payload['timezone'] ?? $this->targetTimezone($target);
        [$requestedStartsAt, $requestedEndsAt] = $this->utcRange($payload['requested_starts_at'], $payload['requested_ends_at'], $timezone);

        if ($requestedEndsAt->lessThanOrEqualTo($requestedStartsAt)) {
            throw ValidationException::withMessages([
                'requested_ends_at' => 'The requested end time must be after the requested start time.',
            ]);
        }

        $scheduleChangeRequest = ScheduleChangeRequest::create([
            'requester_id' => $actor->id,
            'student_id' => $target->student_id,
            'teacher_id' => $target->teacher_id,
            'lesson_id' => $target instanceof Lesson ? $target->id : null,
            'class_schedule_id' => $target instanceof ClassSchedule ? $target->id : null,
            'current_starts_at' => $this->targetStartsAt($target),
            'current_ends_at' => $this->targetEndsAt($target),
            'requested_starts_at' => $requestedStartsAt,
            'requested_ends_at' => $requestedEndsAt,
            'timezone' => $timezone,
            'reason' => $payload['reason'],
            'status' => ScheduleChangeRequest::STATUS_PENDING,
        ]);

        if (! $this->approvalRequired()) {
            return $this->approve($scheduleChangeRequest, $actor, 'Automatically approved because schedule change approval is disabled.');
        }

        return $scheduleChangeRequest->refresh();
    }

    /**
     * Approve and apply a pending schedule change request.
     *
     * The request row is locked, the target is verified unchanged, and the
     * associated class schedule or lesson time is updated before the request is
     * marked approved.
     *
     * @throws ValidationException
     */
    public function approve(ScheduleChangeRequest $scheduleChangeRequest, User $reviewer, ?string $notes = null): ScheduleChangeRequest
    {
        return DB::transaction(function () use ($scheduleChangeRequest, $reviewer, $notes): ScheduleChangeRequest {
            $lockedRequest = ScheduleChangeRequest::query()
                ->whereKey($scheduleChangeRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertPending($lockedRequest);

            if ($lockedRequest->class_schedule_id) {
                $schedule = ClassSchedule::query()
                    ->whereKey($lockedRequest->class_schedule_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->assertTargetUnchanged($lockedRequest, $schedule);

                $this->classScheduleService->update($schedule, [
                    'starts_at' => $lockedRequest->requested_starts_at,
                    'ends_at' => $lockedRequest->requested_ends_at,
                    'timezone' => $lockedRequest->timezone,
                ], $reviewer);
            } else {
                $lesson = Lesson::query()
                    ->whereKey($lockedRequest->lesson_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->assertTargetUnchanged($lockedRequest, $lesson);

                $lesson->forceFill([
                    'start_time' => $lockedRequest->requested_starts_at,
                    'end_time' => $lockedRequest->requested_ends_at,
                ])->save();
            }

            $lockedRequest->update([
                'status' => ScheduleChangeRequest::STATUS_APPROVED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ]);

            return $lockedRequest->refresh();
        });
    }

    /**
     * Reject a pending schedule change request.
     *
     * The request status, reviewer, review timestamp, and optional notes are
     * persisted. The underlying lesson or class schedule is not changed.
     *
     * @throws ValidationException
     */
    public function reject(ScheduleChangeRequest $scheduleChangeRequest, User $reviewer, ?string $notes = null): ScheduleChangeRequest
    {
        $this->assertPending($scheduleChangeRequest);

        $scheduleChangeRequest->update([
            'status' => ScheduleChangeRequest::STATUS_REJECTED,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);

        return $scheduleChangeRequest->refresh();
    }

    /**
     * Cancel a pending schedule change request.
     *
     * The request is marked cancelled with an optional note and no schedule or
     * lesson time is updated.
     *
     * @throws ValidationException
     */
    public function cancel(ScheduleChangeRequest $scheduleChangeRequest, ?string $notes = null): ScheduleChangeRequest
    {
        $this->assertPending($scheduleChangeRequest);

        $scheduleChangeRequest->update([
            'status' => ScheduleChangeRequest::STATUS_CANCELLED,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);

        return $scheduleChangeRequest->refresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function targetFromPayload(array $payload): Lesson|ClassSchedule
    {
        if (isset($payload['lesson_id'], $payload['class_schedule_id'])) {
            throw ValidationException::withMessages([
                'lesson_id' => 'Use either lesson_id or class_schedule_id, not both.',
            ]);
        }

        if (isset($payload['lesson_id'])) {
            return Lesson::query()->findOrFail($payload['lesson_id']);
        }

        if (isset($payload['class_schedule_id'])) {
            return ClassSchedule::query()->findOrFail($payload['class_schedule_id']);
        }

        throw ValidationException::withMessages([
            'class_schedule_id' => 'A lesson_id or class_schedule_id is required.',
        ]);
    }

    private function assertCanRequestForTarget(User $actor, Lesson|ClassSchedule $target): void
    {
        if ($actor->hasRole('student') && (int) $target->student_id !== (int) $actor->id) {
            throw ValidationException::withMessages([
                'class_schedule_id' => 'Students can only request schedule changes for their own classes.',
            ]);
        }

        if ($actor->hasRole('teacher') && (int) $target->teacher_id !== (int) $actor->id) {
            throw ValidationException::withMessages([
                'class_schedule_id' => 'Teachers can only request schedule changes for their own classes.',
            ]);
        }
    }

    private function assertPending(ScheduleChangeRequest $scheduleChangeRequest): void
    {
        if ($scheduleChangeRequest->status !== ScheduleChangeRequest::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => 'Only pending schedule change requests can be reviewed.',
            ]);
        }
    }

    private function assertTargetUnchanged(ScheduleChangeRequest $scheduleChangeRequest, Lesson|ClassSchedule $target): void
    {
        if (
            ! $this->sameInstant($scheduleChangeRequest->current_starts_at, $this->targetStartsAt($target))
            || ! $this->sameInstant($scheduleChangeRequest->current_ends_at, $this->targetEndsAt($target))
        ) {
            throw ValidationException::withMessages([
                'status' => 'The related class schedule changed after this request was submitted.',
            ]);
        }
    }

    private function approvalRequired(): bool
    {
        return (bool) $this->portalSettings->value(self::APPROVAL_SETTING);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function utcRange(mixed $startsAt, mixed $endsAt, string $timezone): array
    {
        return [
            CarbonImmutable::parse($startsAt, $timezone)->utc(),
            CarbonImmutable::parse($endsAt, $timezone)->utc(),
        ];
    }

    private function targetTimezone(Lesson|ClassSchedule $target): string
    {
        if ($target instanceof ClassSchedule) {
            return $target->timezone;
        }

        return $target->student?->timezone ?? $target->teacher?->timezone ?? 'UTC';
    }

    private function targetStartsAt(Lesson|ClassSchedule $target): ?CarbonInterface
    {
        return $target instanceof ClassSchedule ? $target->starts_at : $target->start_time;
    }

    private function targetEndsAt(Lesson|ClassSchedule $target): ?CarbonInterface
    {
        return $target instanceof ClassSchedule ? $target->ends_at : $target->end_time;
    }

    private function sameInstant(?CarbonInterface $left, ?CarbonInterface $right): bool
    {
        if ($left === null || $right === null) {
            return $left === null && $right === null;
        }

        return $left->getTimestamp() === $right->getTimestamp();
    }
}
