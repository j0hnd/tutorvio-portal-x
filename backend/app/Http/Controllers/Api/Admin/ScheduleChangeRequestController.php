<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ScheduleChangeRequests\ScheduleChangeRequestResource;
use App\Models\ScheduleChangeRequest;
use App\Services\Scheduling\ScheduleChangeRequestService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ScheduleChangeRequestController extends Controller
{
    public function __construct(private readonly ScheduleChangeRequestService $scheduleChangeRequests) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ScheduleChangeRequest::class);

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

    public function pending(Request $request): JsonResponse
    {
        $request->merge(['status' => ScheduleChangeRequest::STATUS_PENDING]);

        return $this->index($request);
    }

    public function show(ScheduleChangeRequest $scheduleChangeRequest): JsonResponse
    {
        Gate::authorize('view', $scheduleChangeRequest);

        return response()->json([
            'data' => new ScheduleChangeRequestResource(
                $scheduleChangeRequest->load(['requester', 'student', 'teacher', 'reviewer', 'lesson', 'classSchedule'])
            ),
        ]);
    }

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
