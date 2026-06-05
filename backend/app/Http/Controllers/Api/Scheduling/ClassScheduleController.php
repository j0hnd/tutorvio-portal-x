<?php

namespace App\Http\Controllers\Api\Scheduling;

use App\Http\Controllers\Controller;
use App\Http\Resources\Scheduling\ClassScheduleResource;
use App\Models\Scheduling\ClassSchedule;
use App\Models\User;
use App\Services\Scheduling\ClassScheduleService;
use App\Support\PublicIdResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ClassScheduleController extends Controller
{
    /**
     * Create the controller with its service dependencies.
     *
     * The framework resolves this constructor before action-specific route
     * middleware, permissions, validation, and authorization are applied.
     */
    public function __construct(private readonly ClassScheduleService $scheduleService) {}

    /**
     * Display a filtered list of class schedule records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * Inline validation rejects missing or invalid request data before processing. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON response containing the requested data.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ClassSchedule::class);
        $request->merge(PublicIdResolver::resolveFields($request->all(), [
            'teacher_id' => User::class,
            'student_id' => User::class,
        ]));

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
            ->with(['student:id,public_id,name,email,timezone', 'teacher:id,public_id,name,email,timezone', 'reminders'])
            ->when($validated['teacher_id'] ?? null, fn ($query, int $teacherId) => $query->where('teacher_id', $teacherId))
            ->when($validated['student_id'] ?? null, fn ($query, int $studentId) => $query->where('student_id', $studentId))
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($validated['from'] ?? null, fn ($query, string $from) => $query->where('starts_at', '>=', $from))
            ->when($validated['to'] ?? null, fn ($query, string $to) => $query->where('starts_at', '<=', $to))
            ->when($user->hasRole('teacher') && ! $user->hasAnyRole(['admin', 'staff']), fn ($query) => $query->where('teacher_id', $user->id))
            ->when($user->hasRole('student') && ! $user->hasAnyRole(['admin', 'staff']), fn ($query) => $query->where('student_id', $user->id))
            ->orderBy('starts_at')
            ->paginate($validated['per_page'] ?? 25);

        return response()->json(
            $schedules->through(fn (ClassSchedule $schedule) => new ClassScheduleResource($schedule))
        );
    }

    /**
     * Create a new class schedule record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON payload with the created resource or action result.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', ClassSchedule::class);

        $schedule = $this->scheduleService->create($this->validatePayload($request, true), $request->user());

        return response()->json([
            'data' => new ClassScheduleResource($schedule->load(['student:id,public_id,name,email,timezone', 'teacher:id,public_id,name,email,timezone'])),
        ], 201);
    }

    /**
     * Handle the recurring action for class schedule records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * Inline validation rejects missing or invalid request data before processing. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON response containing the requested data.
     */
    public function recurring(Request $request): JsonResponse
    {
        Gate::authorize('create', ClassSchedule::class);
        $request->merge(PublicIdResolver::resolveFields($request->all(), [
            'student_id' => User::class,
            'teacher_id' => User::class,
        ]));

        $validated = $request->validate([
            'student_id' => ['required', 'integer', 'exists:users,id'],
            'teacher_id' => ['required', 'integer', 'exists:users,id'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'string', Rule::in(ClassSchedule::STATUSES)],
            'class_type' => ['sometimes', 'string', Rule::in(ClassSchedule::CLASS_TYPES)],
            'timezone' => ['required', 'string', Rule::in(timezone_identifiers_list())],
            'start_date' => ['required', 'date'],
            'end_date' => ['required_without:occurrence_count', 'date'],
            'occurrence_count' => ['required_without:end_date', 'integer', 'min:1', 'max:260'],
            'day_of_week' => ['required_without:days_of_week', 'integer', 'min:0', 'max:6'],
            'days_of_week' => ['required_without:day_of_week', 'array', 'min:1', 'max:7'],
            'days_of_week.*' => ['integer', 'distinct', 'min:0', 'max:6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'meeting_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        if (isset($validated['end_date'], $validated['occurrence_count'])) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'occurrence_count' => ['Use either end_date or occurrence_count, not both.'],
                ],
            ], 422);
        }

        if (isset($validated['day_of_week'], $validated['days_of_week'])) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'days_of_week' => ['Use either day_of_week or days_of_week, not both.'],
                ],
            ], 422);
        }

        $result = $this->scheduleService->createRecurring($validated, $request->user());

        return response()->json([
            'data' => [
                'created_count' => count($result['created']),
                'skipped_count' => count($result['skipped']),
                'requested_occurrences' => $result['requested_occurrences'],
                'created' => collect($result['created'])
                    ->map(fn (ClassSchedule $schedule) => new ClassScheduleResource($schedule->load(['student:id,public_id,name,email,timezone', 'teacher:id,public_id,name,email,timezone'])))
                    ->values(),
                'skipped' => $result['skipped'],
            ],
        ], 201);
    }

    /**
     * Display the selected class schedule record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Route model parameters include $classSchedule.
     * Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON response containing the requested data.
     */
    public function show(ClassSchedule $classSchedule): JsonResponse
    {
        Gate::authorize('view', $classSchedule);

        return response()->json([
            'data' => new ClassScheduleResource($classSchedule->load(['student:id,public_id,name,email,timezone', 'teacher:id,public_id,name,email,timezone', 'reminders'])),
        ]);
    }

    /**
     * Update the selected class schedule record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $classSchedule.
     * Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON payload with the updated resource or status result.
     */
    public function update(Request $request, ClassSchedule $classSchedule): JsonResponse
    {
        Gate::authorize('update', $classSchedule);

        $schedule = $this->scheduleService->update($classSchedule, $this->validatePayload($request, false), $request->user());

        return response()->json([
            'data' => new ClassScheduleResource($schedule->load(['student:id,public_id,name,email,timezone', 'teacher:id,public_id,name,email,timezone'])),
        ]);
    }

    /**
     * Delete the selected class schedule record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Route model parameters include $classSchedule.
     * Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON confirmation after deletion.
     */
    public function destroy(ClassSchedule $classSchedule): JsonResponse
    {
        Gate::authorize('delete', $classSchedule);

        $classSchedule->delete();

        return response()->json(status: 204);
    }

    /**
     * Cancel the selected class schedule record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $classSchedule.
     * Inline validation rejects missing or invalid request data before processing. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON payload with the updated resource or status result.
     */
    public function cancel(Request $request, ClassSchedule $classSchedule): JsonResponse
    {
        Gate::authorize('cancel', $classSchedule);

        $validated = $request->validate([
            'reason' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        return response()->json([
            'data' => new ClassScheduleResource(
                $this->scheduleService->cancel($classSchedule, $request->user(), $validated['reason'] ?? null)
                    ->load(['student:id,public_id,name,email,timezone', 'teacher:id,public_id,name,email,timezone'])
            ),
        ]);
    }

    /**
     * Handle the reschedule action for class schedule records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $classSchedule.
     * Inline validation rejects missing or invalid request data before processing. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON response containing the requested data.
     */
    public function reschedule(Request $request, ClassSchedule $classSchedule): JsonResponse
    {
        Gate::authorize('reschedule', $classSchedule);
        $request->merge(PublicIdResolver::resolveFields($request->all(), [
            'teacher_id' => User::class,
            'student_id' => User::class,
        ]));

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
            'class_type' => ['sometimes', 'string', Rule::in(ClassSchedule::CLASS_TYPES)],
        ]);

        return response()->json([
            'data' => new ClassScheduleResource(
                $this->scheduleService->reschedule($classSchedule, $validated, $request->user())
                    ->load(['student:id,public_id,name,email,timezone', 'teacher:id,public_id,name,email,timezone'])
            ),
        ], 201);
    }

    /**
     * Handle the status action for class schedule records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $classSchedule.
     * Inline validation rejects missing or invalid request data before processing. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON response containing the requested data.
     */
    public function status(Request $request, ClassSchedule $classSchedule): JsonResponse
    {
        Gate::authorize('update', $classSchedule);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(ClassSchedule::STATUSES)],
        ]);

        return response()->json([
            'data' => new ClassScheduleResource(
                $this->scheduleService->updateStatus($classSchedule, $validated['status'], $request->user())
                    ->load(['student:id,public_id,name,email,timezone', 'teacher:id,public_id,name,email,timezone'])
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, bool $creating): array
    {
        $request->merge(PublicIdResolver::resolveFields($request->all(), [
            'student_id' => User::class,
            'teacher_id' => User::class,
        ]));

        return $request->validate([
            'student_id' => [$creating ? 'required' : 'sometimes', 'integer', 'exists:users,id'],
            'teacher_id' => [$creating ? 'required' : 'sometimes', 'integer', 'exists:users,id'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'string', Rule::in(ClassSchedule::STATUSES)],
            'class_type' => ['sometimes', 'string', Rule::in(ClassSchedule::CLASS_TYPES)],
            'timezone' => [$creating ? 'required' : 'sometimes', 'string', Rule::in(timezone_identifiers_list())],
            'starts_at' => [$creating ? 'required' : 'sometimes', 'date'],
            'ends_at' => [$creating ? 'required' : 'sometimes', 'date'],
            'meeting_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);
    }
}
