<?php

namespace App\Http\Controllers\Api\Scheduling;

use App\Http\Controllers\Controller;
use App\Http\Resources\Scheduling\TeacherAvailabilityResource;
use App\Models\Scheduling\TeacherAvailability;
use App\Services\Scheduling\TeacherAvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TeacherAvailabilityController extends Controller
{
    /**
     * Create the controller with its service dependencies.
     *
     * The framework resolves this constructor before action-specific route
     * middleware, permissions, validation, and authorization are applied.
     *
     * @param  TeacherAvailabilityService  $availabilityService
     */
    public function __construct(private readonly TeacherAvailabilityService $availabilityService) {}

    /**
     * Display a filtered list of teacher availability records.
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
        Gate::authorize('viewAny', TeacherAvailability::class);

        $validated = $request->validate([
            'teacher_id' => ['sometimes', 'integer', 'exists:users,id'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user();
        $assignedTeacherId = $user->studentProfile?->assigned_teacher_id;

        $availabilities = TeacherAvailability::query()
            ->with('teacher:id,public_id,name,email,timezone')
            ->when($validated['teacher_id'] ?? null, fn ($query, int $teacherId) => $query->where('teacher_id', $teacherId))
            ->when($user->hasRole('teacher') && ! $user->hasAnyRole(['admin', 'staff']), fn ($query) => $query->where('teacher_id', $user->id))
            ->when($user->hasRole('student') && ! $user->hasAnyRole(['admin', 'staff']), fn ($query) => $query->where('teacher_id', $assignedTeacherId ?? 0))
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->paginate($validated['per_page'] ?? 25);

        return response()->json(
            $availabilities->through(fn (TeacherAvailability $availability) => new TeacherAvailabilityResource($availability))
        );
    }

    /**
     * Create a new teacher availability record.
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
        Gate::authorize('create', TeacherAvailability::class);

        $validated = $this->validatePayload($request, true);
        $this->assertTeacherCanManage($request, $validated['teacher_id']);

        return response()->json([
            'data' => new TeacherAvailabilityResource($this->availabilityService->createAvailability($validated)->load('teacher:id,public_id,name,email,timezone')),
        ], 201);
    }

    /**
     * Display the selected teacher availability record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Route model parameters include $teacherAvailability.
     * Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON response containing the requested data.
     *
     * @param  TeacherAvailability  $teacherAvailability
     * @return JsonResponse
     */
    public function show(TeacherAvailability $teacherAvailability): JsonResponse
    {
        Gate::authorize('view', $teacherAvailability);

        return response()->json(['data' => new TeacherAvailabilityResource($teacherAvailability->load('teacher:id,public_id,name,email,timezone'))]);
    }

    /**
     * Update the selected teacher availability record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $teacherAvailability.
     * Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON payload with the updated resource or status result.
     *
     * @param  Request  $request
     * @param  TeacherAvailability  $teacherAvailability
     * @return JsonResponse
     */
    public function update(Request $request, TeacherAvailability $teacherAvailability): JsonResponse
    {
        Gate::authorize('update', $teacherAvailability);

        $validated = $this->validatePayload($request, false);
        $this->assertTeacherCanManage($request, $validated['teacher_id'] ?? $teacherAvailability->teacher_id);

        return response()->json([
            'data' => new TeacherAvailabilityResource($this->availabilityService->updateAvailability($teacherAvailability, $validated)->load('teacher:id,public_id,name,email,timezone')),
        ]);
    }

    /**
     * Delete the selected teacher availability record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Route model parameters include $teacherAvailability.
     * Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON confirmation after deletion.
     *
     * @param  TeacherAvailability  $teacherAvailability
     * @return JsonResponse
     */
    public function destroy(TeacherAvailability $teacherAvailability): JsonResponse
    {
        Gate::authorize('delete', $teacherAvailability);

        $this->availabilityService->assertAvailabilityHasNoBookedSchedules($teacherAvailability);

        $teacherAvailability->delete();

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
            'teacher_id' => [$creating ? 'required' : 'sometimes', 'integer', 'exists:users,id'],
            'day_of_week' => [$creating ? 'required' : 'sometimes', 'integer', 'min:0', 'max:6'],
            'start_time' => [$creating ? 'required' : 'sometimes', 'date_format:H:i'],
            'end_time' => [$creating ? 'required' : 'sometimes', 'date_format:H:i'],
            'timezone' => [$creating ? 'required' : 'sometimes', 'string', Rule::in(timezone_identifiers_list())],
            'effective_from' => ['sometimes', 'nullable', 'date'],
            'effective_until' => ['sometimes', 'nullable', 'date', 'after_or_equal:effective_from'],
            'capacity' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);
    }

    /**
     * Handle the assert teacher can manage action for teacher availability records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $teacherId.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @param  Request  $request
     * @param  int  $teacherId
     * @return void
     */
    private function assertTeacherCanManage(Request $request, int $teacherId): void
    {
        if ($request->user()->hasRole('teacher') && ! $request->user()->hasAnyRole(['admin', 'staff']) && $request->user()->id !== $teacherId) {
            throw ValidationException::withMessages([
                'teacher_id' => 'Teachers can only manage their own availability.',
            ]);
        }
    }
}
