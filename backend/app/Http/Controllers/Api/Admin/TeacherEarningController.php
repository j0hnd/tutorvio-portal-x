<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\TeacherEarnings\TeacherEarningResource;
use App\Models\LessonRecord;
use App\Models\TeacherCompensation;
use App\Models\TeacherEarning;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TeacherEarningController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', TeacherEarning::class);

        $validated = $this->validatedFilters($request);

        $earnings = $this->filteredQuery($validated)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 15);

        return response()->json($earnings->through(fn (TeacherEarning $earning) => new TeacherEarningResource($earning)));
    }

    public function teacher(Request $request, User $teacher): JsonResponse
    {
        Gate::authorize('viewAny', TeacherEarning::class);

        abort_unless($teacher->hasRole('teacher'), 404);

        $validated = $this->validatedFilters($request);
        $validated['teacher_id'] = $teacher->id;

        $earnings = $this->filteredQuery($validated)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 15);

        return response()->json($earnings->through(fn (TeacherEarning $earning) => new TeacherEarningResource($earning)));
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'teacher_id' => ['sometimes', 'integer', 'exists:users,id'],
            'teacher' => ['sometimes', 'integer', 'exists:users,id'],
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
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<TeacherEarning>
     */
    private function filteredQuery(array $filters): Builder
    {
        $teacherId = $filters['teacher_id'] ?? $filters['teacher'] ?? null;
        $courseProgramId = $filters['course_program_id'] ?? $filters['course'] ?? null;

        return TeacherEarning::query()
            ->with(['teacher', 'lessonRecord'])
            ->when($teacherId, fn (Builder $query, int $teacherId) => $query->where('teacher_id', $teacherId))
            ->when($filters['payout_period'] ?? null, fn (Builder $query, string $payoutPeriod) => $query->where('calculation_metadata->payout_period', $payoutPeriod))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $this->whereEarningDate($query, '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $this->whereEarningDate($query, '<=', $date))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['lesson_type'] ?? null, function (Builder $query, string $lessonType): void {
                $query->where(function (Builder $query) use ($lessonType): void {
                    $query
                        ->whereHas('lessonRecord', fn (Builder $query) => $query->where('lesson_type', $lessonType))
                        ->orWhere('calculation_metadata->lesson_type', $lessonType);
                });
            })
            ->when($courseProgramId, fn (Builder $query, int $courseProgramId) => $query->where('calculation_metadata->course_program_id', $courseProgramId))
            ->when($filters['pay_model'] ?? null, fn (Builder $query, string $payModel) => $query->where('pay_model', $payModel));
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
