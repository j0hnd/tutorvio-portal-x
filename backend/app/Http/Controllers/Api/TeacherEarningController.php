<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TeacherEarnings\TeacherEarningResource;
use App\Models\LessonRecord;
use App\Models\TeacherCompensation;
use App\Models\TeacherEarning;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TeacherEarningController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewOwn', TeacherEarning::class);

        $validated = $request->validate([
            'payout_period' => ['sometimes', 'string', 'max:50'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
            'status' => ['sometimes', 'string', Rule::in(TeacherEarning::STATUSES)],
            'lesson_type' => ['sometimes', 'string', Rule::in(LessonRecord::LESSON_TYPES)],
            'course' => ['sometimes', 'integer', 'exists:course_programs,id'],
            'course_program_id' => ['sometimes', 'integer', 'exists:course_programs,id'],
            'pay_model' => ['sometimes', 'string', Rule::in(TeacherCompensation::PAY_MODELS)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $courseProgramId = $validated['course_program_id'] ?? $validated['course'] ?? null;

        $earnings = TeacherEarning::query()
            ->with(['teacher', 'lessonRecord'])
            ->where('teacher_id', $request->user()->id)
            ->when($validated['payout_period'] ?? null, fn (Builder $query, string $payoutPeriod) => $query->where('calculation_metadata->payout_period', $payoutPeriod))
            ->when($validated['date_from'] ?? null, fn (Builder $query, string $date) => $this->whereEarningDate($query, '>=', $date))
            ->when($validated['date_to'] ?? null, fn (Builder $query, string $date) => $this->whereEarningDate($query, '<=', $date))
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($validated['lesson_type'] ?? null, function (Builder $query, string $lessonType): void {
                $query->where(function (Builder $query) use ($lessonType): void {
                    $query
                        ->whereHas('lessonRecord', fn (Builder $query) => $query->where('lesson_type', $lessonType))
                        ->orWhere('calculation_metadata->lesson_type', $lessonType);
                });
            })
            ->when($courseProgramId, fn (Builder $query, int $courseProgramId) => $query->where('calculation_metadata->course_program_id', $courseProgramId))
            ->when($validated['pay_model'] ?? null, fn (Builder $query, string $payModel) => $query->where('pay_model', $payModel))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 15);

        return response()->json($earnings->through(fn (TeacherEarning $earning) => new TeacherEarningResource($earning)));
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
