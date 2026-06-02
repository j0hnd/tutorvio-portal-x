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
    /**
     * Create the controller with its service dependencies.
     *
     * The framework resolves this constructor before action-specific route
     * middleware, permissions, validation, and authorization are applied.
     *
     * @param  ScheduleChangeRequestService  $scheduleChangeRequests
     */
    public function __construct(private readonly ScheduleChangeRequestService $scheduleChangeRequests) {}

    /**
     * Display a filtered list of schedule change request records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * Inline validation rejects missing or invalid request data before processing.
     * Returns a JSON response containing the requested data.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(ScheduleChangeRequest::STATUSES)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user();

        $requests = ScheduleChangeRequest::query()
            ->with(['requester', 'student', 'teacher', 'lesson', 'classSchedule'])
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

    /**
     * Create a new schedule change request record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * Inline validation rejects missing or invalid request data before processing. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON payload with the created resource or action result.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
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
                $scheduleChangeRequest->load(['requester', 'student', 'teacher', 'lesson', 'classSchedule'])
            ),
        ], 201);
    }

    /**
     * Display the selected schedule change request record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * The ScheduleChangeRequest handles authorization and validation before the controller action runs. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON response containing the requested data.
     *
     * @param  ScheduleChangeRequest  $scheduleChangeRequest
     * @return JsonResponse
     */
    public function show(ScheduleChangeRequest $scheduleChangeRequest): JsonResponse
    {
        Gate::authorize('view', $scheduleChangeRequest);

        return response()->json([
            'data' => new ScheduleChangeRequestResource(
                $scheduleChangeRequest->load(['requester', 'student', 'teacher', 'lesson', 'classSchedule'])
            ),
        ]);
    }

    /**
     * Cancel the selected schedule change request record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * The ScheduleChangeRequest handles authorization and validation before the controller action runs. Inline validation rejects missing or invalid request data before processing. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON payload with the updated resource or status result.
     *
     * @param  Request  $request
     * @param  ScheduleChangeRequest  $scheduleChangeRequest
     * @return JsonResponse
     */
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
                    ->load(['requester', 'student', 'teacher', 'lesson', 'classSchedule'])
            ),
        ]);
    }
}
