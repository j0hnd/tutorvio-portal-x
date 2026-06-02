<?php

namespace App\Http\Controllers\Api\Scheduling;

use App\Http\Controllers\Controller;
use App\Http\Resources\Scheduling\ScheduleReminderResource;
use App\Models\Scheduling\ScheduleReminder;
use App\Services\Scheduling\ScheduleReminderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ScheduleReminderController extends Controller
{
    /**
     * Create the controller with its service dependencies.
     *
     * The framework resolves this constructor before action-specific route
     * middleware, permissions, validation, and authorization are applied.
     *
     * @param  ScheduleReminderService  $reminderService
     */
    public function __construct(private readonly ScheduleReminderService $reminderService) {}

    /**
     * Display a filtered list of schedule reminder records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * Inline validation rejects missing or invalid request data before processing. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON response containing the requested data.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ScheduleReminder::class);

        $validated = $request->validate([
            'class_schedule_id' => ['sometimes', 'integer', 'exists:class_schedules,id'],
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'status' => ['sometimes', 'string', Rule::in(ScheduleReminder::STATUSES)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user();

        $reminders = ScheduleReminder::query()
            ->with(['classSchedule', 'user:id,public_id,name,email,timezone'])
            ->when($validated['class_schedule_id'] ?? null, fn ($query, int $scheduleId) => $query->where('class_schedule_id', $scheduleId))
            ->when($validated['user_id'] ?? null, fn ($query, int $userId) => $query->where('user_id', $userId))
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when(! $user->hasAnyRole(['admin', 'staff']), fn ($query) => $query->where('user_id', $user->id))
            ->orderBy('scheduled_for')
            ->paginate($validated['per_page'] ?? 25);

        return response()->json(
            $reminders->through(fn (ScheduleReminder $reminder) => new ScheduleReminderResource($reminder))
        );
    }

    /**
     * Create a new schedule reminder record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON payload with the created resource or action result.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', ScheduleReminder::class);

        return response()->json([
            'data' => new ScheduleReminderResource($this->reminderService->create($this->validatePayload($request, true))->load(['classSchedule', 'user:id,public_id,name,email,timezone'])),
        ], 201);
    }

    /**
     * Display the selected schedule reminder record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Route model parameters include $scheduleReminder.
     * Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON response containing the requested data.
     *
     * @param  ScheduleReminder  $scheduleReminder
     * @return JsonResponse
     */
    public function show(ScheduleReminder $scheduleReminder): JsonResponse
    {
        Gate::authorize('view', $scheduleReminder);

        return response()->json(['data' => new ScheduleReminderResource($scheduleReminder->load(['classSchedule', 'user:id,public_id,name,email,timezone']))]);
    }

    /**
     * Update the selected schedule reminder record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $scheduleReminder.
     * Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON payload with the updated resource or status result.
     *
     * @param  Request  $request
     * @param  ScheduleReminder  $scheduleReminder
     * @return JsonResponse
     */
    public function update(Request $request, ScheduleReminder $scheduleReminder): JsonResponse
    {
        Gate::authorize('update', $scheduleReminder);

        return response()->json([
            'data' => new ScheduleReminderResource($this->reminderService->update($scheduleReminder, $this->validatePayload($request, false))->load(['classSchedule', 'user:id,public_id,name,email,timezone'])),
        ]);
    }

    /**
     * Delete the selected schedule reminder record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Route model parameters include $scheduleReminder.
     * Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON confirmation after deletion.
     *
     * @param  ScheduleReminder  $scheduleReminder
     * @return JsonResponse
     */
    public function destroy(ScheduleReminder $scheduleReminder): JsonResponse
    {
        Gate::authorize('delete', $scheduleReminder);

        $scheduleReminder->delete();

        return response()->json(status: 204);
    }

    /**
     * @return array<string, mixed>
     *
     * @param  Request  $request
     * @param  bool  $creating
     */
    private function validatePayload(Request $request, bool $creating): array
    {
        return $request->validate([
            'class_schedule_id' => [$creating ? 'required' : 'sometimes', 'integer', 'exists:class_schedules,id'],
            'user_id' => [$creating ? 'required' : 'sometimes', 'integer', 'exists:users,id'],
            'channel' => ['sometimes', 'string', Rule::in(ScheduleReminder::CHANNELS)],
            'status' => ['sometimes', 'string', Rule::in(ScheduleReminder::STATUSES)],
            'scheduled_for' => [$creating ? 'required' : 'sometimes', 'date'],
            'timezone' => ['sometimes', 'string', Rule::in(timezone_identifiers_list())],
            'sent_at' => ['sometimes', 'nullable', 'date'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ]);
    }
}
