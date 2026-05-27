<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TeacherAssignments\StoreTeacherStudentAssignmentRequest;
use App\Http\Requests\TeacherAssignments\UpdateTeacherStudentAssignmentRequest;
use App\Http\Resources\TeacherAssignments\TeacherStudentAssignmentResource;
use App\Models\TeacherStudentAssignment;
use App\Models\User;
use App\Services\TeacherStudentAssignmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TeacherStudentAssignmentController extends Controller
{
    public function __construct(private readonly TeacherStudentAssignmentService $assignments) {}

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

    public function show(TeacherStudentAssignment $teacherStudentAssignment): JsonResponse
    {
        Gate::authorize('view', $teacherStudentAssignment);

        return response()->json([
            'data' => new TeacherStudentAssignmentResource(
                $teacherStudentAssignment->load(['student', 'teacher', 'assignedBy'])
            ),
        ]);
    }

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
