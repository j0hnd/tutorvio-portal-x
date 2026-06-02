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
    public function __construct(private readonly TeacherWorkloadService $workloads) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewTeacherWorkloads');

        $validated = $this->validatedFilters($request);

        return response()->json([
            'data' => $this->workloads->summaries($request->user(), $validated),
        ]);
    }

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
