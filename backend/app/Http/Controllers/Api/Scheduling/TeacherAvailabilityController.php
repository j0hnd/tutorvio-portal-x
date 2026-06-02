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
    public function __construct(private readonly TeacherAvailabilityService $availabilityService) {}

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

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', TeacherAvailability::class);

        $validated = $this->validatePayload($request, true);
        $this->assertTeacherCanManage($request, $validated['teacher_id']);

        return response()->json([
            'data' => new TeacherAvailabilityResource($this->availabilityService->createAvailability($validated)->load('teacher:id,public_id,name,email,timezone')),
        ], 201);
    }

    public function show(TeacherAvailability $teacherAvailability): JsonResponse
    {
        Gate::authorize('view', $teacherAvailability);

        return response()->json(['data' => new TeacherAvailabilityResource($teacherAvailability->load('teacher:id,public_id,name,email,timezone'))]);
    }

    public function update(Request $request, TeacherAvailability $teacherAvailability): JsonResponse
    {
        Gate::authorize('update', $teacherAvailability);

        $validated = $this->validatePayload($request, false);
        $this->assertTeacherCanManage($request, $validated['teacher_id'] ?? $teacherAvailability->teacher_id);

        return response()->json([
            'data' => new TeacherAvailabilityResource($this->availabilityService->updateAvailability($teacherAvailability, $validated)->load('teacher:id,public_id,name,email,timezone')),
        ]);
    }

    public function destroy(TeacherAvailability $teacherAvailability): JsonResponse
    {
        Gate::authorize('delete', $teacherAvailability);

        $this->availabilityService->assertAvailabilityHasNoBookedSchedules($teacherAvailability);

        $teacherAvailability->delete();

        return response()->json(status: 204);
    }

    /**
     * @return array<string, mixed>
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

    private function assertTeacherCanManage(Request $request, int $teacherId): void
    {
        if ($request->user()->hasRole('teacher') && ! $request->user()->hasAnyRole(['admin', 'staff']) && $request->user()->id !== $teacherId) {
            throw ValidationException::withMessages([
                'teacher_id' => 'Teachers can only manage their own availability.',
            ]);
        }
    }
}
