<?php

namespace App\Services;

use App\Models\CourseProgramStudentAssignment;
use App\Models\LessonRecord;
use App\Models\Subscription;
use App\Models\TeacherCompensation;
use App\Models\TeacherCompensationRateRule;
use App\Models\TeacherEarning;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

class TeacherEarningCalculationService
{
    /**
     * @return SupportCollection<int, TeacherEarning>
     */
    public function calculateForTeacher(User|int $teacher, ?string $dateFrom = null, ?string $dateTo = null): SupportCollection
    {
        $teacherId = $teacher instanceof User ? $teacher->id : $teacher;
        $earnings = collect();

        LessonRecord::query()
            ->where('teacher_id', $teacherId)
            ->where('lesson_status', LessonRecord::STATUS_COMPLETED)
            ->where('is_completed', true)
            ->when($dateFrom !== null, fn ($query) => $query->whereDate('scheduled_date', '>=', $dateFrom))
            ->when($dateTo !== null, fn ($query) => $query->whereDate('scheduled_date', '<=', $dateTo))
            ->chunkById(100, function (Collection $lessonRecords) use ($earnings): void {
                $lessonRecords->each(function (LessonRecord $lessonRecord) use ($earnings): void {
                    $earning = $this->calculateForLessonRecord($lessonRecord);

                    if ($earning !== null) {
                        $earnings->push($earning);
                    }
                });
            });

        return $earnings;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function calculateForLessonRecord(LessonRecord $lessonRecord, array $context = []): ?TeacherEarning
    {
        if (! $this->isEligibleLessonRecord($lessonRecord)) {
            return null;
        }

        return DB::transaction(function () use ($lessonRecord, $context): TeacherEarning {
            $existing = TeacherEarning::query()
                ->where('source_type', TeacherEarning::SOURCE_LESSON_RECORD)
                ->where('source_id', $lessonRecord->id)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $lessonRecord->loadMissing([
                'lessonBalanceConsumedSubscription',
                'student.courseProgramAssignments.courseProgram.courseType',
                'teacher.teacherProfile',
            ]);

            $compensation = $this->compensationFor($lessonRecord);
            $courseAssignment = $this->courseAssignmentFor($lessonRecord);
            $metadataContext = $this->metadataContext($lessonRecord);
            $calculationContext = [
                ...$metadataContext,
                ...$context,
                'lesson_type' => $lessonRecord->lesson_type,
                'course_program_id' => $context['course_program_id'] ?? $metadataContext['course_program_id'] ?? $courseAssignment?->course_program_id,
                'course_type_id' => $context['course_type_id'] ?? $metadataContext['course_type_id'] ?? $courseAssignment?->courseProgram?->course_type_id,
            ];

            $rule = $this->matchingRateRule($compensation, $calculationContext);
            $payModel = $rule?->pay_model ?? $compensation->pay_model;
            $rate = (float) ($rule?->pay_rate ?? $compensation->default_pay_rate);
            $quantity = $this->quantityFor($payModel, $lessonRecord, $courseAssignment !== null);
            $amount = round($rate * $quantity, 2);

            return TeacherEarning::create([
                'teacher_id' => $lessonRecord->teacher_id,
                'lesson_record_id' => $lessonRecord->id,
                'source_type' => TeacherEarning::SOURCE_LESSON_RECORD,
                'source_id' => $lessonRecord->id,
                'pay_model' => $payModel,
                'rate_used' => $rate,
                'quantity' => $quantity,
                'amount' => $amount,
                'currency' => strtoupper((string) ($rule?->currency ?? $compensation->currency)),
                'calculation_metadata' => [
                    'teacher_compensation_id' => $compensation->id,
                    'teacher_compensation_rate_rule_id' => $rule?->id,
                    'lesson_status' => $lessonRecord->lesson_status,
                    'is_completed' => $lessonRecord->is_completed,
                    'lesson_type' => $lessonRecord->lesson_type,
                    'scheduled_date' => $lessonRecord->scheduled_date?->toDateString(),
                    'start_time' => $lessonRecord->start_time,
                    'end_time' => $lessonRecord->end_time,
                    'duration_hours' => $this->durationHours($lessonRecord),
                    'student_id' => $lessonRecord->student_id,
                    'course_program_id' => $calculationContext['course_program_id'] ?? null,
                    'course_type_id' => $calculationContext['course_type_id'] ?? null,
                    'experience_level' => $calculationContext['experience_level'] ?? null,
                    'contract_agreement' => $calculationContext['contract_agreement'] ?? null,
                    'lesson_balance_consumed_subscription_id' => $lessonRecord->lesson_balance_consumed_subscription_id,
                ],
                'status' => TeacherEarning::STATUS_PENDING,
            ]);
        });
    }

    public function isEligibleLessonRecord(LessonRecord $lessonRecord): bool
    {
        $lessonRecord->loadMissing('lessonBalanceConsumedSubscription');

        return $lessonRecord->lesson_status === LessonRecord::STATUS_COMPLETED
            && $lessonRecord->is_completed
            && $lessonRecord->lesson_balance_consumed_subscription_id !== null
            && $lessonRecord->lessonBalanceConsumedSubscription?->payment_status === Subscription::PAYMENT_STATUS_PAID;
    }

    private function compensationFor(LessonRecord $lessonRecord): TeacherCompensation
    {
        return TeacherCompensation::query()
            ->active()
            ->effectiveOn($lessonRecord->scheduled_date->toDateString())
            ->where('teacher_id', $lessonRecord->teacher_id)
            ->with('rateRules')
            ->orderByDesc('effective_start_date')
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function matchingRateRule(TeacherCompensation $compensation, array $context): ?TeacherCompensationRateRule
    {
        /** @var Collection<int, TeacherCompensationRateRule> $rules */
        $rules = $compensation->rateRules
            ->where('is_active', true)
            ->filter(fn (TeacherCompensationRateRule $rule): bool => $this->ruleMatches($rule, $context));

        return $rules
            ->sortBy([
                ['priority', 'desc'],
                fn (TeacherCompensationRateRule $a, TeacherCompensationRateRule $b): int => $this->specificity($b) <=> $this->specificity($a),
                ['id', 'asc'],
            ])
            ->first();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function ruleMatches(TeacherCompensationRateRule $rule, array $context): bool
    {
        foreach (['lesson_type', 'experience_level', 'contract_agreement', 'course_type_id', 'course_program_id'] as $field) {
            if ($rule->{$field} !== null && (string) $rule->{$field} !== (string) ($context[$field] ?? '')) {
                return false;
            }
        }

        return true;
    }

    private function specificity(TeacherCompensationRateRule $rule): int
    {
        return collect([
            $rule->lesson_type,
            $rule->experience_level,
            $rule->contract_agreement,
            $rule->course_type_id,
            $rule->course_program_id,
        ])->filter(fn ($value): bool => $value !== null)->count();
    }

    private function courseAssignmentFor(LessonRecord $lessonRecord): ?CourseProgramStudentAssignment
    {
        return $lessonRecord->student
            ?->courseProgramAssignments()
            ->active()
            ->with('courseProgram.courseType')
            ->where(function ($query) use ($lessonRecord) {
                $query
                    ->whereNull('start_date')
                    ->orWhereDate('start_date', '<=', $lessonRecord->scheduled_date);
            })
            ->latest('start_date')
            ->latest('assigned_at')
            ->first();
    }

    private function quantityFor(string $payModel, LessonRecord $lessonRecord, bool $hasCourseAssignment): float
    {
        return match ($payModel) {
            TeacherCompensation::PAY_MODEL_PER_HOUR => $this->durationHours($lessonRecord),
            TeacherCompensation::PAY_MODEL_PER_STUDENT => 1.0,
            TeacherCompensation::PAY_MODEL_PER_COURSE => $hasCourseAssignment ? 1.0 : 0.0,
            default => 1.0,
        };
    }

    private function durationHours(LessonRecord $lessonRecord): float
    {
        $start = CarbonImmutable::parse($lessonRecord->scheduled_date->toDateString().' '.$lessonRecord->start_time);
        $end = CarbonImmutable::parse($lessonRecord->scheduled_date->toDateString().' '.$lessonRecord->end_time);

        return max(0, round($start->diffInMinutes($end) / 60, 2));
    }

    /**
     * @return array<string, mixed>
     */
    private function metadataContext(LessonRecord $lessonRecord): array
    {
        $metadata = $lessonRecord->meeting_metadata ?? [];
        $compensation = is_array($metadata) && is_array($metadata['compensation'] ?? null)
            ? $metadata['compensation']
            : [];

        return array_intersect_key($compensation, array_flip([
            'experience_level',
            'contract_agreement',
            'course_type_id',
            'course_program_id',
        ]));
    }
}
