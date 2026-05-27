<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\TeacherChangeRequests\TeacherChangeRequestResource;
use App\Models\TeacherChangeRequest;
use App\Models\User;
use App\Services\TeacherStudentAssignmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TeacherChangeRequestController extends Controller
{
    public function __construct(private readonly TeacherStudentAssignmentService $assignments) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', TeacherChangeRequest::class);

        $validated = $request->validate([
            'student_id' => ['sometimes', 'integer', 'exists:users,id'],
            'current_teacher_id' => ['sometimes', 'integer', 'exists:users,id'],
            'status' => ['sometimes', 'string', Rule::in(TeacherChangeRequest::STATUSES)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $requests = TeacherChangeRequest::query()
            ->with(['student', 'currentTeacher', 'approvedTeacher', 'reviewer'])
            ->when($validated['student_id'] ?? null, fn (Builder $query, int $studentId) => $query->where('student_id', $studentId))
            ->when($validated['current_teacher_id'] ?? null, fn (Builder $query, int $teacherId) => $query->where('current_teacher_id', $teacherId))
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->latest()
            ->paginate($validated['per_page'] ?? 15);

        return response()->json($requests->through(fn (TeacherChangeRequest $teacherChangeRequest) => new TeacherChangeRequestResource($teacherChangeRequest)));
    }

    public function pending(Request $request): JsonResponse
    {
        $request->merge(['status' => TeacherChangeRequest::STATUS_PENDING]);

        return $this->index($request);
    }

    public function show(TeacherChangeRequest $teacherChangeRequest): JsonResponse
    {
        Gate::authorize('view', $teacherChangeRequest);

        return response()->json([
            'data' => new TeacherChangeRequestResource(
                $teacherChangeRequest->load(['student', 'currentTeacher', 'approvedTeacher', 'reviewer'])
            ),
        ]);
    }

    public function approve(Request $request, TeacherChangeRequest $teacherChangeRequest): JsonResponse
    {
        Gate::authorize('update', $teacherChangeRequest);

        $validated = $request->validate([
            'new_teacher_id' => ['sometimes', 'integer', 'different:current_teacher_id', 'exists:users,id'],
            'reassign' => ['sometimes', 'boolean'],
            'review_reason' => ['nullable', 'string', 'max:5000'],
            'admin_notes' => ['nullable', 'string', 'max:10000'],
            'assigned_at' => ['sometimes', 'date'],
        ]);

        $this->assertPending($teacherChangeRequest);

        $newTeacher = isset($validated['new_teacher_id'])
            ? User::query()->findOrFail($validated['new_teacher_id'])
            : null;

        if ($newTeacher && ($newTeacher->status !== User::STATUS_ACTIVE || ! $newTeacher->hasRole('teacher'))) {
            throw ValidationException::withMessages([
                'new_teacher_id' => 'The selected new teacher must be an active teacher.',
            ]);
        }

        if ($newTeacher && (int) $newTeacher->id === (int) $teacherChangeRequest->current_teacher_id) {
            throw ValidationException::withMessages([
                'new_teacher_id' => 'The selected new teacher must be different from the current teacher.',
            ]);
        }

        $shouldReassign = $newTeacher && ($validated['reassign'] ?? true);

        DB::transaction(function () use ($request, $teacherChangeRequest, $validated, $newTeacher, $shouldReassign) {
            $lockedRequest = TeacherChangeRequest::query()
                ->whereKey($teacherChangeRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertPending($lockedRequest);

            if ($shouldReassign && $newTeacher) {
                $this->assignments->assign($lockedRequest->student, $newTeacher, $request->user(), [
                    'assigned_at' => $validated['assigned_at'] ?? now(),
                    'reason' => 'Teacher change request #'.$lockedRequest->id.' approved.',
                    'notes' => $validated['admin_notes'] ?? null,
                    'previous_assignment_notes' => $validated['review_reason'] ?? null,
                ]);
            }

            $lockedRequest->update([
                'approved_teacher_id' => $newTeacher?->id,
                'status' => TeacherChangeRequest::STATUS_APPROVED,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_reason' => $validated['review_reason'] ?? null,
                'admin_notes' => $validated['admin_notes'] ?? null,
            ]);
        });

        return response()->json([
            'data' => new TeacherChangeRequestResource(
                $teacherChangeRequest->refresh()->load(['student', 'currentTeacher', 'approvedTeacher', 'reviewer'])
            ),
        ]);
    }

    public function reject(Request $request, TeacherChangeRequest $teacherChangeRequest): JsonResponse
    {
        Gate::authorize('update', $teacherChangeRequest);

        $validated = $request->validate([
            'review_reason' => ['nullable', 'string', 'max:5000'],
            'admin_notes' => ['nullable', 'string', 'max:10000'],
        ]);

        $this->assertPending($teacherChangeRequest);

        $teacherChangeRequest->update([
            'status' => TeacherChangeRequest::STATUS_REJECTED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_reason' => $validated['review_reason'] ?? null,
            'admin_notes' => $validated['admin_notes'] ?? null,
        ]);

        return response()->json([
            'data' => new TeacherChangeRequestResource(
                $teacherChangeRequest->refresh()->load(['student', 'currentTeacher', 'approvedTeacher', 'reviewer'])
            ),
        ]);
    }

    private function assertPending(TeacherChangeRequest $teacherChangeRequest): void
    {
        if ($teacherChangeRequest->status !== TeacherChangeRequest::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => 'Only pending teacher change requests can be reviewed.',
            ]);
        }
    }
}
