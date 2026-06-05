<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LessonRecord;
use App\Models\Scheduling\ClassSchedule;
use App\Models\User;
use App\Services\TeacherWorkloadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TeacherWorkloadController extends Controller
{
    /**
     * Create the controller with its service dependencies.
     *
     * The framework resolves this constructor before action-specific route
     * middleware, permissions, validation, and authorization are applied.
     *
     * @param  TeacherWorkloadService  $workloads
     */
    public function __construct(private readonly TeacherWorkloadService $workloads) {}

    /**
     * Display a filtered list of teacher workload records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON response containing the requested data.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewTeacherWorkloads');

        $validated = $this->validatedFilters($request);

        return response()->json([
            'data' => $this->workloads->summaries($request->user(), $validated),
        ]);
    }

    /**
     * Display the selected teacher workload record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $teacher.
     * Authorization checks in this method can reject users who do not own or cannot manage the target record. The method can return a forbidden response when authorization or ownership checks fail.
     * Returns a JSON response containing the requested data.
     *
     * @param  Request  $request
     * @param  User  $teacher
     * @return JsonResponse
     */
    public function show(Request $request, User $teacher): JsonResponse
    {
        abort_unless($teacher->hasRole('teacher'), 404);
        Gate::authorize('viewTeacherWorkload', $teacher);

        return response()->json([
            'data' => $this->workloads->summary($teacher->load('teacherProfile'), $this->validatedFilters($request)),
        ]);
    }

    /**
     * @return array<string, mixed>
     *
     * @param  Request  $request
     */
    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'timezone' => ['sometimes', 'string', Rule::in(timezone_identifiers_list())],
            'teacher_status' => ['sometimes', 'string', Rule::in(User::STATUSES)],
            'capacity_status' => ['sometimes', 'string', Rule::in(TeacherWorkloadService::STATUSES)],
            'lesson_type' => ['sometimes', 'string', Rule::in([...ClassSchedule::CLASS_TYPES, ...LessonRecord::LESSON_TYPES])],
            'slot_minutes' => ['sometimes', 'integer', 'min:15', 'max:240'],
        ]);
    }
}
