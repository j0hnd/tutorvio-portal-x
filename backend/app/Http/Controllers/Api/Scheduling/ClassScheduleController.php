<?php

namespace App\Http\Controllers\Api\Scheduling;

use App\Http\Controllers\Controller;
use App\Models\Scheduling\ClassSchedule;
use App\Services\Scheduling\ClassScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ClassScheduleController extends Controller
{
    public function __construct(private readonly ClassScheduleService $scheduleService) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ClassSchedule::class);

        $validated = $request->validate([
            'teacher_id' => ['sometimes', 'integer', 'exists:users,id'],
            'student_id' => ['sometimes', 'integer', 'exists:users,id'],
            'status' => ['sometimes', 'string', Rule::in(ClassSchedule::STATUSES)],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user();

        $schedules = ClassSchedule::query()
            ->with(['student:id,name,email,timezone', 'teacher:id,name,email,timezone', 'reminders'])
            ->when($validated['teacher_id'] ?? null, fn ($query, int $teacherId) => $query->where('teacher_id', $teacherId))
            ->when($validated['student_id'] ?? null, fn ($query, int $studentId) => $query->where('student_id', $studentId))
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($validated['from'] ?? null, fn ($query, string $from) => $query->where('starts_at', '>=', $from))
            ->when($validated['to'] ?? null, fn ($query, string $to) => $query->where('starts_at', '<=', $to))
            ->when($user->hasRole('teacher') && ! $user->hasAnyRole(['admin', 'staff']), fn ($query) => $query->where('teacher_id', $user->id))
            ->when($user->hasRole('student') && ! $user->hasAnyRole(['admin', 'staff']), fn ($query) => $query->where('student_id', $user->id))
            ->orderBy('starts_at')
            ->paginate($validated['per_page'] ?? 25);

        return response()->json($schedules);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', ClassSchedule::class);

        $schedule = $this->scheduleService->create($this->validatePayload($request, true), $request->user());

        return response()->json(['data' => $schedule->load(['student:id,name,email,timezone', 'teacher:id,name,email,timezone'])], 201);
    }

    public function show(ClassSchedule $classSchedule): JsonResponse
    {
        Gate::authorize('view', $classSchedule);

        return response()->json([
            'data' => $classSchedule->load(['student:id,name,email,timezone', 'teacher:id,name,email,timezone', 'reminders']),
        ]);
    }

    public function update(Request $request, ClassSchedule $classSchedule): JsonResponse
    {
        Gate::authorize('update', $classSchedule);

        $schedule = $this->scheduleService->update($classSchedule, $this->validatePayload($request, false), $request->user());

        return response()->json(['data' => $schedule->load(['student:id,name,email,timezone', 'teacher:id,name,email,timezone'])]);
    }

    public function destroy(ClassSchedule $classSchedule): JsonResponse
    {
        Gate::authorize('delete', $classSchedule);

        $classSchedule->delete();

        return response()->json(status: 204);
    }

    public function cancel(Request $request, ClassSchedule $classSchedule): JsonResponse
    {
        Gate::authorize('cancel', $classSchedule);

        $validated = $request->validate([
            'reason' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        return response()->json([
            'data' => $this->scheduleService->cancel($classSchedule, $request->user(), $validated['reason'] ?? null),
        ]);
    }

    public function reschedule(Request $request, ClassSchedule $classSchedule): JsonResponse
    {
        Gate::authorize('reschedule', $classSchedule);

        $validated = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date'],
            'timezone' => ['sometimes', 'string', Rule::in(timezone_identifiers_list())],
            'teacher_id' => ['sometimes', 'integer', 'exists:users,id'],
            'student_id' => ['sometimes', 'integer', 'exists:users,id'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'meeting_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'string', Rule::in(ClassSchedule::STATUSES)],
        ]);

        return response()->json([
            'data' => $this->scheduleService->reschedule($classSchedule, $validated, $request->user()),
        ], 201);
    }

    public function status(Request $request, ClassSchedule $classSchedule): JsonResponse
    {
        Gate::authorize('update', $classSchedule);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(ClassSchedule::STATUSES)],
        ]);

        return response()->json([
            'data' => $this->scheduleService->updateStatus($classSchedule, $validated['status'], $request->user()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, bool $creating): array
    {
        return $request->validate([
            'student_id' => [$creating ? 'required' : 'sometimes', 'integer', 'exists:users,id'],
            'teacher_id' => [$creating ? 'required' : 'sometimes', 'integer', 'exists:users,id'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'string', Rule::in(ClassSchedule::STATUSES)],
            'timezone' => [$creating ? 'required' : 'sometimes', 'string', Rule::in(timezone_identifiers_list())],
            'starts_at' => [$creating ? 'required' : 'sometimes', 'date'],
            'ends_at' => [$creating ? 'required' : 'sometimes', 'date'],
            'meeting_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);
    }
}
