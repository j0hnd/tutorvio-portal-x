<?php

namespace App\Services\Payroll;

use App\Models\PayoutPeriod;
use App\Models\TeacherEarning;
use App\Models\TeacherPayoutAdjustment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PayoutReportService
{
    /**
     * @return array<string, mixed>
     */
    public function forPeriod(PayoutPeriod $period): array
    {
        $earnings = TeacherEarning::query()
            ->with(['teacher', 'lessonRecord'])
            ->whereHas('payoutPeriods', fn (Builder $query) => $query->whereKey($period->id))
            ->get();

        $adjustments = TeacherPayoutAdjustment::query()
            ->with(['teacher', 'payoutPeriod', 'createdBy'])
            ->where('payout_period_id', $period->id)
            ->get();

        return [
            'scope' => 'period',
            'payout_period' => [
                'id' => $period->public_id,
                'name' => $period->name,
                'start_date' => $period->start_date?->toDateString(),
                'end_date' => $period->end_date?->toDateString(),
                'status' => $period->status,
            ],
            ...$this->buildReport($earnings, $adjustments),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function forTeacher(User $teacher, array $filters = []): array
    {
        $earnings = TeacherEarning::query()
            ->with(['teacher', 'lessonRecord'])
            ->where('teacher_id', $teacher->id)
            ->when($filters['payout_period_id'] ?? null, function (Builder $query, int $periodId): void {
                $query->whereHas('payoutPeriods', fn (Builder $query) => $query->whereKey($periodId));
            })
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $this->whereEarningDate($query, '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $this->whereEarningDate($query, '<=', $date))
            ->get();

        $adjustments = TeacherPayoutAdjustment::query()
            ->with(['teacher', 'payoutPeriod', 'createdBy'])
            ->where('teacher_id', $teacher->id)
            ->when($filters['payout_period_id'] ?? null, fn (Builder $query, int $periodId) => $query->where('payout_period_id', $periodId))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->get();

        return [
            'scope' => 'teacher',
            'teacher' => [
                'id' => $teacher->public_id,
                'name' => $teacher->name,
            ],
            ...$this->buildReport($earnings, $adjustments),
        ];
    }

    /**
     * @param  Collection<int, TeacherEarning>  $earnings
     * @param  Collection<int, TeacherPayoutAdjustment>  $adjustments
     * @return array<string, mixed>
     */
    private function buildReport(Collection $earnings, Collection $adjustments): array
    {
        $teacherIds = $earnings->pluck('teacher_id')
            ->merge($adjustments->pluck('teacher_id'))
            ->unique()
            ->values();

        $teachers = $teacherIds
            ->map(fn (int $teacherId): array => $this->teacherSummary(
                $teacherId,
                $earnings->where('teacher_id', $teacherId),
                $adjustments->where('teacher_id', $teacherId)
            ))
            ->values();

        return [
            'breakdown' => $this->sumBreakdowns($teachers),
            'teachers' => $teachers,
        ];
    }

    /**
     * @param  Collection<int, TeacherEarning>  $earnings
     * @param  Collection<int, TeacherPayoutAdjustment>  $adjustments
     * @return array<string, mixed>
     */
    private function teacherSummary(int $teacherId, Collection $earnings, Collection $adjustments): array
    {
        $teacher = $earnings->first()?->teacher ?? $adjustments->first()?->teacher;
        $baseEarnings = $earnings->sum(fn (TeacherEarning $earning): float => $this->isVariableEarning($earning) ? 0.0 : (float) $earning->amount);
        $variableEarnings = $earnings->sum(fn (TeacherEarning $earning): float => $this->isVariableEarning($earning) ? (float) $earning->amount : 0.0);
        $manualAdditions = $adjustments->sum(fn (TeacherPayoutAdjustment $adjustment): float => max(0.0, (float) $adjustment->amount));
        $manualDeductions = abs($adjustments->sum(fn (TeacherPayoutAdjustment $adjustment): float => min(0.0, (float) $adjustment->amount)));

        return [
            'teacher_id' => $teacher?->public_id,
            'teacher_name' => $teacher?->name,
            'breakdown' => [
                'base_earnings' => round($baseEarnings, 2),
                'variable_rate_earnings' => round($variableEarnings, 2),
                'manual_additions' => round($manualAdditions, 2),
                'manual_deductions' => round($manualDeductions, 2),
                'total_payout_amount' => round($baseEarnings + $variableEarnings + $manualAdditions - $manualDeductions, 2),
            ],
            'earning_ids' => $earnings->pluck('id')->values(),
            'adjustment_ids' => $adjustments->pluck('id')->values(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $teachers
     * @return array<string, float>
     */
    private function sumBreakdowns(Collection $teachers): array
    {
        return [
            'base_earnings' => round($teachers->sum('breakdown.base_earnings'), 2),
            'variable_rate_earnings' => round($teachers->sum('breakdown.variable_rate_earnings'), 2),
            'manual_additions' => round($teachers->sum('breakdown.manual_additions'), 2),
            'manual_deductions' => round($teachers->sum('breakdown.manual_deductions'), 2),
            'total_payout_amount' => round($teachers->sum('breakdown.total_payout_amount'), 2),
        ];
    }

    private function isVariableEarning(TeacherEarning $earning): bool
    {
        $metadata = is_array($earning->calculation_metadata) ? $earning->calculation_metadata : [];

        return ! empty($metadata['teacher_compensation_rate_rule_id']);
    }

    private function whereEarningDate(Builder $query, string $operator, string $date): void
    {
        $query->where(function (Builder $query) use ($operator, $date): void {
            $query
                ->whereHas('lessonRecord', fn (Builder $query) => $query->whereDate('scheduled_date', $operator, $date))
                ->orWhere(function (Builder $query) use ($operator, $date): void {
                    $query
                        ->whereNull('lesson_record_id')
                        ->whereDate('created_at', $operator, $date);
                });
        });
    }
}
