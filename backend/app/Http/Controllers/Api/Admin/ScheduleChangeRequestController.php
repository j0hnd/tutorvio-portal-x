<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ScheduleChangeRequests\ScheduleChangeRequestResource;
use App\Models\Lesson;
use App\Models\ScheduleChangeRequest;
use App\Models\Scheduling\ClassSchedule;
use App\Models\User;
use App\Services\Scheduling\ScheduleChangeRequestService;
use App\Support\PublicIdResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ScheduleChangeRequestController extends Controller
{
    /**
     * Create the controller with its service dependencies.
     *
     * The framework resolves this constructor before action-specific route
     * middleware, permissions, validation, and authorization are applied.
     */
    public function __construct(private readonly ScheduleChangeRequestService $scheduleChangeRequests) {}

    /**
     * Display a filtered list of schedule change request records.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * Inline validation rejects missing or invalid request data before processing. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON response containing the requested data.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ScheduleChangeRequest::class);
        $request->merge(PublicIdResolver::resolveFields($request->all(), [
            'requester_id' => User::class,
            'student_id' => User::class,
            'teacher_id' => User::class,
            'lesson_id' => Lesson::class,
            'class_schedule_id' => ClassSchedule::class,
        ]));

        $validated = $request->validate([
            'requester_id' => ['sometimes', 'integer', 'exists:users,id'],
            'student_id' => ['sometimes', 'integer', 'exists:users,id'],
            'teacher_id' => ['sometimes', 'integer', 'exists:users,id'],
            'lesson_id' => ['sometimes', 'integer', 'exists:lessons,id'],
            'class_schedule_id' => ['sometimes', 'integer', 'exists:class_schedules,id'],
            'status' => ['sometimes', 'string', Rule::in(ScheduleChangeRequest::STATUSES)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $requests = ScheduleChangeRequest::query()
            ->with(['requester', 'student', 'teacher', 'reviewer', 'lesson', 'classSchedule'])
            ->when($validated['requester_id'] ?? null, fn (Builder $query, int $id) => $query->where('requester_id', $id))
            ->when($validated['student_id'] ?? null, fn (Builder $query, int $id) => $query->where('student_id', $id))
            ->when($validated['teacher_id'] ?? null, fn (Builder $query, int $id) => $query->where('teacher_id', $id))
            ->when($validated['lesson_id'] ?? null, fn (Builder $query, int $id) => $query->where('lesson_id', $id))
            ->when($validated['class_schedule_id'] ?? null, fn (Builder $query, int $id) => $query->where('class_schedule_id', $id))
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->latest()
            ->paginate($validated['per_page'] ?? 15);

        return response()->json($requests->through(fn (ScheduleChangeRequest $request) => new ScheduleChangeRequestResource($request)));
    }

    /**
     * Display pending schedule change request records.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     */
    public function pending(Request $request): JsonResponse
    {
        $request->merge(['status' => ScheduleChangeRequest::STATUS_PENDING]);

        return $this->index($request);
    }

    /**
     * Display the selected schedule change request record.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * The ScheduleChangeRequest handles authorization and validation before the controller action runs. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON response containing the requested data.
     */
    public function show(ScheduleChangeRequest $scheduleChangeRequest): JsonResponse
    {
        Gate::authorize('view', $scheduleChangeRequest);

        return response()->json([
            'data' => new ScheduleChangeRequestResource(
                $scheduleChangeRequest->load(['requester', 'student', 'teacher', 'reviewer', 'lesson', 'classSchedule'])
            ),
        ]);
    }

    /**
     * Approve the selected schedule change request record.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * The ScheduleChangeRequest handles authorization and validation before the controller action runs. Inline validation rejects missing or invalid request data before processing. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON payload with the updated resource or status result.
     */
    public function approve(Request $request, ScheduleChangeRequest $scheduleChangeRequest): JsonResponse
    {
        Gate::authorize('update', $scheduleChangeRequest);

        $validated = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        return response()->json([
            'data' => new ScheduleChangeRequestResource(
                $this->scheduleChangeRequests
                    ->approve($scheduleChangeRequest, $request->user(), $validated['review_notes'] ?? null)
                    ->load(['requester', 'student', 'teacher', 'reviewer', 'lesson', 'classSchedule'])
            ),
        ]);
    }

    /**
     * Reject the selected schedule change request record.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * The ScheduleChangeRequest handles authorization and validation before the controller action runs. Inline validation rejects missing or invalid request data before processing. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON payload with the updated resource or status result.
     */
    public function reject(Request $request, ScheduleChangeRequest $scheduleChangeRequest): JsonResponse
    {
        Gate::authorize('update', $scheduleChangeRequest);

        $validated = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        return response()->json([
            'data' => new ScheduleChangeRequestResource(
                $this->scheduleChangeRequests
                    ->reject($scheduleChangeRequest, $request->user(), $validated['review_notes'] ?? null)
                    ->load(['requester', 'student', 'teacher', 'reviewer', 'lesson', 'classSchedule'])
            ),
        ]);
    }
}
