<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CourseProgram;
use App\Models\LessonRecord;
use App\Models\Scheduling\ClassSchedule;
use App\Models\User;
use App\Services\TeacherWorkloadService;
use App\Support\PublicIdResolver;
use App\Support\Reports\ReportResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TeacherLoadReportController extends Controller
{
    /**
     * Create the controller with its service dependencies.
     *
     * The framework resolves this constructor before action-specific route
     * middleware, permissions, validation, and authorization are applied.
     */
    public function __construct(private readonly TeacherWorkloadService $workloads) {}

    /**
     * Handle the teacher load report endpoint.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON response containing the requested data.
     */
    public function __invoke(Request $request): JsonResponse
    {
        Gate::authorize('viewTeacherWorkloads');

        $validated = $this->validatedFilters($request);
        $rows = collect($this->workloads->summaries($request->user(), $validated))
            ->map(fn (array $summary): array => $this->reportRow($summary))
            ->values();

        $payload = ReportResponse::payload([
            'summary' => $this->summary($rows),
            'rows' => $rows->all(),
            'filters' => $this->reportFilters($validated),
        ], $this->pagination($validated));

        return response()->json([
            ...$payload,
            'data' => [
                ...$payload,
                'summary' => $rows->all(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedFilters(Request $request): array
    {
        $input = $request->all();
        $input = [
            ...$input,
            ...PublicIdResolver::resolveFields($input, [
                'teacher_id' => User::class,
                'course_id' => CourseProgram::class,
            ]),
        ];

        foreach (['date_from' => 'from', 'date_to' => 'to'] as $source => $target) {
            if (! array_key_exists($source, $input) || array_key_exists($target, $input)) {
                continue;
            }

            $input[$target] = $input[$source];
        }

        foreach (['from', 'to'] as $key) {
            if (! isset($input[$key])) {
                continue;
            }

            try {
                $input[$key] = Carbon::parse($input[$key])->toDateString();
            } catch (\Throwable) {
                continue;
            }
        }

        return validator($input, [
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
            'timezone' => ['sometimes', 'string', Rule::in(timezone_identifiers_list())],
            'teacher_id' => ['sometimes', 'integer', 'exists:users,id'],
            'course_id' => ['sometimes', 'integer', 'exists:course_programs,id'],
            'status' => ['sometimes', 'string', Rule::in([
                ...ClassSchedule::STATUSES,
                ...LessonRecord::STATUSES,
                ...LessonRecord::ATTENDANCE_STATUSES,
            ])],
            'teacher_status' => ['sometimes', 'string', Rule::in(User::STATUSES)],
            'capacity_status' => ['sometimes', 'string', Rule::in(TeacherWorkloadService::STATUSES)],
            'lesson_type' => ['sometimes', 'string', Rule::in([...ClassSchedule::CLASS_TYPES, ...LessonRecord::LESSON_TYPES])],
            'slot_minutes' => ['sometimes', 'integer', 'min:15', 'max:240'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ])->validate();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, int|float>
     */
    private function summary(Collection $rows): array
    {
        return [
            'teachers_count' => $rows->count(),
            'active_assigned_students_count' => $rows->sum('active_assigned_students_count'),
            'scheduled_lessons_count' => $rows->sum('scheduled_lessons_count'),
            'completed_lessons_count' => $rows->sum('completed_lessons_count'),
            'missed_cancelled_rescheduled_lessons_count' => $rows->sum('missed_cancelled_rescheduled_lessons_count'),
            'available_open_slots' => $rows->sum('available_open_slots'),
            'average_capacity_value' => $rows->count() === 0 ? 0.0 : round($rows->sum('capacity_value') / $rows->count(), 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    private function reportRow(array $summary): array
    {
        return [
            'teacher_id' => $summary['teacher_id'],
            'teacher_name' => $summary['teacher_name'],
            'active_assigned_students_count' => $summary['active_student_count'],
            'scheduled_lessons_count' => $summary['scheduled_class_count'],
            'completed_lessons_count' => $summary['completed_lesson_count'],
            'missed_cancelled_rescheduled_lessons_count' => $summary['missed_cancelled_rescheduled_lesson_count'],
            'capacity_value' => $summary['maximum_student_capacity'],
            'available_open_slots' => $summary['available_slots_count'],
            'workload_status' => $summary['workload_status'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function reportFilters(array $filters): array
    {
        return array_filter([
            'date_from' => $filters['from'] ?? null,
            'date_to' => $filters['to'] ?? null,
            'timezone' => $filters['timezone'] ?? null,
            'teacher_id' => isset($filters['teacher_id']) ? (int) $filters['teacher_id'] : null,
            'course_id' => isset($filters['course_id']) ? (int) $filters['course_id'] : null,
            'status' => $filters['status'] ?? null,
            'teacher_status' => $filters['teacher_status'] ?? null,
            'capacity_status' => $filters['capacity_status'] ?? null,
            'lesson_type' => $filters['lesson_type'] ?? null,
            'slot_minutes' => isset($filters['slot_minutes']) ? (int) $filters['slot_minutes'] : null,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{page: int, per_page: int}
     */
    private function pagination(array $filters): array
    {
        return [
            'page' => (int) ($filters['page'] ?? 1),
            'per_page' => (int) ($filters['per_page'] ?? 50),
        ];
    }
}
