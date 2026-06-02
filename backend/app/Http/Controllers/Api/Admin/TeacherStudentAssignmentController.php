<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TeacherAssignments\StoreTeacherStudentAssignmentRequest;
use App\Http\Requests\TeacherAssignments\UpdateTeacherStudentAssignmentRequest;
use App\Http\Resources\TeacherAssignments\TeacherStudentAssignmentResource;
use App\Models\LessonRecord;
use App\Models\Scheduling\ClassSchedule;
use App\Models\TeacherStudentAssignment;
use App\Models\User;
use App\Services\TeacherSlotDiscoveryService;
use App\Services\TeacherStudentAssignmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TeacherStudentAssignmentController extends Controller
{
    /**
     * Create the controller with its service dependencies.
     *
     * The framework resolves this constructor before action-specific route
     * middleware, permissions, validation, and authorization are applied.
     */
    public function __construct(
        private readonly TeacherStudentAssignmentService $assignments,
        private readonly TeacherSlotDiscoveryService $teacherSlots
    ) {}

    /**
     * Display a filtered list of teacher student assignment records.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Notable request fields include active.
     * Inline validation rejects missing or invalid request data before processing. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON response containing the requested data.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', TeacherStudentAssignment::class);

        $validated = $request->validate([
            'student_id' => ['sometimes', 'integer', 'exists:users,id'],
            'teacher_id' => ['sometimes', 'integer', 'exists:users,id'],
            'status' => ['sometimes', 'string', Rule::in(TeacherStudentAssignment::STATUSES)],
            'active' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $assignments = TeacherStudentAssignment::query()
            ->with(['student', 'teacher', 'assignedBy'])
            ->when($validated['student_id'] ?? null, fn (Builder $query, int $studentId) => $query->where('student_id', $studentId))
            ->when($validated['teacher_id'] ?? null, fn (Builder $query, int $teacherId) => $query->where('teacher_id', $teacherId))
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($request->boolean('active'), fn (Builder $query) => $query->active())
            ->latest('assigned_at')
            ->latest('id')
            ->paginate($validated['per_page'] ?? 15);

        return response()->json($assignments->through(fn (TeacherStudentAssignment $assignment) => new TeacherStudentAssignmentResource($assignment)));
    }

    /**
     * Create a new teacher student assignment record.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * The StoreTeacherStudentAssignmentRequest handles authorization and validation before the controller action runs. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON payload with the created resource or action result.
     */
    public function store(StoreTeacherStudentAssignmentRequest $request): JsonResponse
    {
        Gate::authorize('create', TeacherStudentAssignment::class);

        $validated = $request->validated();
        $student = User::query()->findOrFail($validated['student_id']);
        $teacher = User::query()->findOrFail($validated['teacher_id']);

        $assignment = $this->assignments->assign($student, $teacher, $request->user(), $validated);

        return response()->json([
            'data' => new TeacherStudentAssignmentResource($assignment),
        ], $assignment->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Handle the assign student action for teacher student assignment records.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $student.
     * The StoreTeacherStudentAssignmentRequest handles authorization and validation before the controller action runs. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON response containing the requested data.
     */
    public function assignStudent(StoreTeacherStudentAssignmentRequest $request, User $student): JsonResponse
    {
        Gate::authorize('create', TeacherStudentAssignment::class);

        $validated = $request->validated();
        $teacher = User::query()->findOrFail($validated['teacher_id']);

        $assignment = $this->assignments->assign($student, $teacher, $request->user(), [
            ...$validated,
            'student_id' => $student->id,
        ]);

        return response()->json([
            'data' => new TeacherStudentAssignmentResource($assignment),
        ], $assignment->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Handle the available teachers action for teacher student assignment records.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $student.
     * Inline validation rejects missing or invalid request data before processing. Authorization checks in this method can reject users who do not own or cannot manage the target record. The method can return a forbidden response when authorization or ownership checks fail.
     * Returns a JSON response containing the requested data.
     */
    public function availableTeachers(Request $request, User $student): JsonResponse
    {
        Gate::authorize('viewAny', TeacherStudentAssignment::class);
        abort_unless($student->hasRole('student'), 404);

        $validated = $request->validate([
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'timezone' => ['sometimes', 'string', Rule::in(timezone_identifiers_list())],
            'lesson_type' => ['sometimes', 'string', Rule::in([...ClassSchedule::CLASS_TYPES, ...LessonRecord::LESSON_TYPES])],
            'course' => ['sometimes', 'string', 'max:255'],
            'slot_minutes' => ['sometimes', 'integer', 'min:15', 'max:240'],
            'max_results' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json([
            'data' => $this->teacherSlots->discoverForStudent($student, $validated),
        ]);
    }

    /**
     * Handle the end active action for teacher student assignment records.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $student.
     * Inline validation rejects missing or invalid request data before processing. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON response containing the requested data.
     */
    public function endActive(Request $request, User $student): JsonResponse
    {
        Gate::authorize('create', TeacherStudentAssignment::class);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'ended_at' => ['nullable', 'date'],
        ]);

        $assignment = TeacherStudentAssignment::query()
            ->where('student_id', $student->id)
            ->active()
            ->first();

        if (! $assignment) {
            return response()->json([
                'message' => 'The selected student does not have an active teacher assignment.',
                'errors' => [
                    'student_id' => ['The selected student does not have an active teacher assignment.'],
                ],
            ], 422);
        }

        $assignment = $this->assignments->updateStatus(
            $assignment,
            TeacherStudentAssignment::STATUS_ENDED,
            $validated
        );

        return response()->json([
            'data' => new TeacherStudentAssignmentResource($assignment),
        ]);
    }

    /**
     * Display the selected teacher student assignment record.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Route model parameters include $teacherStudentAssignment.
     * Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON response containing the requested data.
     */
    public function show(TeacherStudentAssignment $teacherStudentAssignment): JsonResponse
    {
        Gate::authorize('view', $teacherStudentAssignment);

        return response()->json([
            'data' => new TeacherStudentAssignmentResource(
                $teacherStudentAssignment->load(['student', 'teacher', 'assignedBy'])
            ),
        ]);
    }

    /**
     * Update the selected teacher student assignment record.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $teacherStudentAssignment.
     * The UpdateTeacherStudentAssignmentRequest handles authorization and validation before the controller action runs. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON payload with the updated resource or status result.
     */
    public function update(
        UpdateTeacherStudentAssignmentRequest $request,
        TeacherStudentAssignment $teacherStudentAssignment
    ): JsonResponse {
        Gate::authorize('update', $teacherStudentAssignment);

        $validated = $request->validated();
        $assignment = $this->assignments->updateStatus($teacherStudentAssignment, $validated['status'], $validated);

        return response()->json([
            'data' => new TeacherStudentAssignmentResource($assignment),
        ]);
    }

    /**
     * Handle the current teacher action for teacher student assignment records.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $student.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     */
    public function currentTeacher(Request $request, User $student): JsonResponse
    {
        $this->authorizeStudentView($request, $student);

        $assignment = TeacherStudentAssignment::query()
            ->with(['student', 'teacher', 'assignedBy'])
            ->where('student_id', $student->id)
            ->active()
            ->first();

        return response()->json([
            'data' => $assignment ? new TeacherStudentAssignmentResource($assignment) : null,
        ]);
    }

    /**
     * Handle the teacher students action for teacher student assignment records.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $teacher.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     */
    public function teacherStudents(Request $request, User $teacher): JsonResponse
    {
        $this->authorizeTeacherView($request, $teacher);

        $assignments = TeacherStudentAssignment::query()
            ->with(['student', 'teacher', 'assignedBy'])
            ->where('teacher_id', $teacher->id)
            ->active()
            ->latest('assigned_at')
            ->latest('id')
            ->get();

        return response()->json([
            'data' => TeacherStudentAssignmentResource::collection($assignments),
        ]);
    }

    /**
     * Handle the student history action for teacher student assignment records.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $student.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     */
    public function studentHistory(Request $request, User $student): JsonResponse
    {
        $this->authorizeStudentView($request, $student);

        $assignments = TeacherStudentAssignment::query()
            ->with(['student', 'teacher', 'assignedBy'])
            ->where('student_id', $student->id)
            ->latest('assigned_at')
            ->latest('id')
            ->get();

        return response()->json([
            'data' => TeacherStudentAssignmentResource::collection($assignments),
        ]);
    }

    /**
     * Handle the teacher history action for teacher student assignment records.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $teacher.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     */
    public function teacherHistory(Request $request, User $teacher): JsonResponse
    {
        $this->authorizeTeacherView($request, $teacher);

        $assignments = TeacherStudentAssignment::query()
            ->with(['student', 'teacher', 'assignedBy'])
            ->where('teacher_id', $teacher->id)
            ->latest('assigned_at')
            ->latest('id')
            ->get();

        return response()->json([
            'data' => TeacherStudentAssignmentResource::collection($assignments),
        ]);
    }

    /**
     * Authorize viewing assignment history for a student.
     *
     * Admins can view any student history. Staff need
     * `teacher_assignments.view`. Students can view only their own history.
     * Teachers and unrelated students are denied.
     */
    private function authorizeStudentView(Request $request, User $student): void
    {
        $user = $request->user();

        abort_unless(
            $user?->hasRole('admin')
                || ($user?->hasRole('staff') && $user->can('teacher_assignments.view'))
                || ($user?->hasRole('student') && (int) $user->id === (int) $student->id),
            403
        );
    }

    /**
     * Authorize viewing assignment history for a teacher.
     *
     * Admins can view any teacher history. Staff need
     * `teacher_assignments.view`. Teachers can view only their own history.
     * Students and unrelated teachers are denied.
     */
    private function authorizeTeacherView(Request $request, User $teacher): void
    {
        $user = $request->user();

        abort_unless(
            $user?->hasRole('admin')
                || ($user?->hasRole('staff') && $user->can('teacher_assignments.view'))
                || ($user?->hasRole('teacher') && (int) $user->id === (int) $teacher->id),
            403
        );
    }
}
