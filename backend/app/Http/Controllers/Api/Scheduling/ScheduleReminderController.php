<?php

namespace App\Http\Controllers\Api\Scheduling;

use App\Http\Controllers\Controller;
use App\Models\Scheduling\ScheduleReminder;
use App\Services\Scheduling\ScheduleReminderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ScheduleReminderController extends Controller
{
    public function __construct(private readonly ScheduleReminderService $reminderService) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ScheduleReminder::class);

        $validated = $request->validate([
            'class_schedule_id' => ['sometimes', 'integer', 'exists:class_schedules,id'],
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'status' => ['sometimes', 'string', Rule::in(ScheduleReminder::STATUSES)],
        ]);

        $user = $request->user();

        return response()->json([
            'data' => ScheduleReminder::query()
                ->with(['classSchedule', 'user:id,name,email,timezone'])
                ->when($validated['class_schedule_id'] ?? null, fn ($query, int $scheduleId) => $query->where('class_schedule_id', $scheduleId))
                ->when($validated['user_id'] ?? null, fn ($query, int $userId) => $query->where('user_id', $userId))
                ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
                ->when(! $user->hasAnyRole(['admin', 'staff']), fn ($query) => $query->where('user_id', $user->id))
                ->orderBy('scheduled_for')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', ScheduleReminder::class);

        return response()->json([
            'data' => $this->reminderService->create($this->validatePayload($request, true))->load(['classSchedule', 'user:id,name,email,timezone']),
        ], 201);
    }

    public function show(ScheduleReminder $scheduleReminder): JsonResponse
    {
        Gate::authorize('view', $scheduleReminder);

        return response()->json(['data' => $scheduleReminder->load(['classSchedule', 'user:id,name,email,timezone'])]);
    }

    public function update(Request $request, ScheduleReminder $scheduleReminder): JsonResponse
    {
        Gate::authorize('update', $scheduleReminder);

        return response()->json([
            'data' => $this->reminderService->update($scheduleReminder, $this->validatePayload($request, false)),
        ]);
    }

    public function destroy(ScheduleReminder $scheduleReminder): JsonResponse
    {
        Gate::authorize('delete', $scheduleReminder);

        $scheduleReminder->delete();

        return response()->json(status: 204);
    }

    /**
     * @return array<string, mixed>
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
