<?php

namespace App\Http\Controllers\Api;

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
        $validated = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(ScheduleChangeRequest::STATUSES)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user();

        $requests = ScheduleChangeRequest::query()
            ->with(['lesson', 'classSchedule'])
            ->where(function (Builder $query) use ($user): void {
                $query->where('requester_id', $user->id)
                    ->orWhere('student_id', $user->id)
                    ->orWhere('teacher_id', $user->id);
            })
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->latest()
            ->paginate($validated['per_page'] ?? 15);

        return response()->json($requests->through(fn (ScheduleChangeRequest $request) => new ScheduleChangeRequestResource($request)));
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', ScheduleChangeRequest::class);

        $validated = $request->validate([
            'lesson_id' => ['required_without:class_schedule_id', 'integer', 'exists:lessons,id', 'prohibits:class_schedule_id'],
            'class_schedule_id' => ['required_without:lesson_id', 'integer', 'exists:class_schedules,id', 'prohibits:lesson_id'],
            'requested_starts_at' => ['required', 'date'],
            'requested_ends_at' => ['required', 'date'],
            'timezone' => ['sometimes', 'string', Rule::in(timezone_identifiers_list())],
            'reason' => ['required', 'string', 'max:5000'],
        ]);

        $scheduleChangeRequest = $this->scheduleChangeRequests->request($validated, $request->user());

        return response()->json([
            'data' => new ScheduleChangeRequestResource(
                $scheduleChangeRequest->load(['lesson', 'classSchedule'])
            ),
        ], 201);
    }

    public function show(ScheduleChangeRequest $scheduleChangeRequest): JsonResponse
    {
        Gate::authorize('view', $scheduleChangeRequest);

        return response()->json([
            'data' => new ScheduleChangeRequestResource(
                $scheduleChangeRequest->load(['lesson', 'classSchedule'])
            ),
        ]);
    }

    public function cancel(Request $request, ScheduleChangeRequest $scheduleChangeRequest): JsonResponse
    {
        Gate::authorize('cancel', $scheduleChangeRequest);

        $validated = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        return response()->json([
            'data' => new ScheduleChangeRequestResource(
                $this->scheduleChangeRequests
                    ->cancel($scheduleChangeRequest, $validated['review_notes'] ?? null)
                    ->load(['lesson', 'classSchedule'])
            ),
        ]);
    }
}
