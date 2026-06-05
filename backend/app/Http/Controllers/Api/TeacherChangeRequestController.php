<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TeacherChangeRequests\TeacherChangeRequestResource;
use App\Models\TeacherChangeRequest;
use App\Models\TeacherStudentAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TeacherChangeRequestController extends Controller
{
    /**
     * Display a filtered list of teacher change request records.
     *
     * Authenticated student users only; route middleware requires the student role.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * Inline validation rejects missing or invalid request data before processing. The method can return a forbidden response when authorization or ownership checks fail.
     * Returns a JSON response containing the requested data.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->hasRole('student'), 403);

        $validated = $request->validate([
            'status' => ['sometimes', 'string', 'in:pending,approved,rejected,cancelled'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $requests = TeacherChangeRequest::query()
            ->with(['student', 'currentTeacher', 'approvedTeacher'])
            ->where('student_id', $user->id)
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->latest()
            ->paginate($validated['per_page'] ?? 15);

        return response()->json($requests->through(fn (TeacherChangeRequest $teacherChangeRequest) => new TeacherChangeRequestResource($teacherChangeRequest)));
    }

    /**
     * Create a new teacher change request record.
     *
     * Authenticated student users only; route middleware requires the student role.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * Inline validation rejects missing or invalid request data before processing. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON payload with the created resource or action result.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', TeacherChangeRequest::class);

        $validated = $request->validate([
            'current_teacher_id' => ['sometimes', 'integer', 'exists:users,id'],
            'requested_reason' => ['required', 'string', 'max:5000'],
            'preferred_schedule_notes' => ['nullable', 'string', 'max:10000'],
        ]);

        $student = $request->user();
        $activeAssignment = TeacherStudentAssignment::query()
            ->where('student_id', $student->id)
            ->active()
            ->first();

        if (! $activeAssignment) {
            throw ValidationException::withMessages([
                'current_teacher_id' => 'You need an active teacher assignment before requesting a teacher change.',
            ]);
        }

        if (
            isset($validated['current_teacher_id'])
            && (int) $validated['current_teacher_id'] !== (int) $activeAssignment->teacher_id
        ) {
            throw ValidationException::withMessages([
                'current_teacher_id' => 'The selected current teacher does not match your active teacher assignment.',
            ]);
        }

        $teacherChangeRequest = TeacherChangeRequest::create([
            'student_id' => $student->id,
            'current_teacher_id' => $activeAssignment->teacher_id,
            'requested_reason' => $validated['requested_reason'],
            'preferred_schedule_notes' => $validated['preferred_schedule_notes'] ?? null,
            'status' => TeacherChangeRequest::STATUS_PENDING,
        ])->load(['student', 'currentTeacher', 'approvedTeacher']);

        return response()->json([
            'data' => new TeacherChangeRequestResource($teacherChangeRequest),
        ], 201);
    }

    /**
     * Display the selected teacher change request record.
     *
     * Authenticated student users only; route middleware requires the student role.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * The TeacherChangeRequest handles authorization and validation before the controller action runs. Authorization checks in this method can reject users who do not own or cannot manage the target record. The method can return a forbidden response when authorization or ownership checks fail.
     * Returns a JSON response containing the requested data.
     *
     * @param  Request  $request
     * @param  TeacherChangeRequest  $teacherChangeRequest
     * @return JsonResponse
     */
    public function show(Request $request, TeacherChangeRequest $teacherChangeRequest): JsonResponse
    {
        Gate::authorize('view', $teacherChangeRequest);
        abort_unless($request->user()?->hasRole('student'), 403);

        return response()->json([
            'data' => new TeacherChangeRequestResource(
                $teacherChangeRequest->load(['student', 'currentTeacher', 'approvedTeacher'])
            ),
        ]);
    }

    /**
     * Cancel the selected teacher change request record.
     *
     * Authenticated student users only; route middleware requires the student role.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * The TeacherChangeRequest handles authorization and validation before the controller action runs. Inline validation rejects missing or invalid request data before processing. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON payload with the updated resource or status result.
     *
     * @param  Request  $request
     * @param  TeacherChangeRequest  $teacherChangeRequest
     * @return JsonResponse
     */
    public function cancel(Request $request, TeacherChangeRequest $teacherChangeRequest): JsonResponse
    {
        Gate::authorize('cancel', $teacherChangeRequest);

        $validated = $request->validate([
            'review_reason' => ['nullable', 'string', 'max:5000'],
        ]);

        $teacherChangeRequest->update([
            'status' => TeacherChangeRequest::STATUS_CANCELLED,
            'reviewed_at' => now(),
            'review_reason' => $validated['review_reason'] ?? null,
        ]);

        return response()->json([
            'data' => new TeacherChangeRequestResource(
                $teacherChangeRequest->refresh()->load(['student', 'currentTeacher', 'approvedTeacher'])
            ),
        ]);
    }
}
