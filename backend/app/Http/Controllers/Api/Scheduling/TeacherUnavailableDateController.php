<?php

namespace App\Http\Controllers\Api\Scheduling;

use App\Http\Controllers\Controller;
use App\Http\Resources\Scheduling\TeacherUnavailableDateResource;
use App\Models\Scheduling\TeacherUnavailableDate;
use App\Services\Scheduling\TeacherAvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TeacherUnavailableDateController extends Controller
{
    public function __construct(private readonly TeacherAvailabilityService $availabilityService) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', TeacherUnavailableDate::class);

        $validated = $request->validate([
            'teacher_id' => ['sometimes', 'integer', 'exists:users,id'],
        ]);

        $user = $request->user();
        $assignedTeacherId = $user->studentProfile?->assigned_teacher_id;

        return response()->json([
            'data' => TeacherUnavailableDateResource::collection(TeacherUnavailableDate::query()
                ->with('teacher:id,public_id,name,email,timezone')
                ->when($validated['teacher_id'] ?? null, fn ($query, int $teacherId) => $query->where('teacher_id', $teacherId))
                ->when($user->hasRole('teacher') && ! $user->hasAnyRole(['admin', 'staff']), fn ($query) => $query->where('teacher_id', $user->id))
                ->when($user->hasRole('student') && ! $user->hasAnyRole(['admin', 'staff']), fn ($query) => $query->where('teacher_id', $assignedTeacherId ?? 0))
                ->orderBy('starts_at')
                ->get()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', TeacherUnavailableDate::class);

        $validated = $this->validatePayload($request, true);
        $this->assertTeacherCanManage($request, $validated['teacher_id']);

        return response()->json([
            'data' => new TeacherUnavailableDateResource($this->availabilityService->createUnavailableDate($validated)->load('teacher:id,public_id,name,email,timezone')),
        ], 201);
    }

    public function show(TeacherUnavailableDate $teacherUnavailableDate): JsonResponse
    {
        Gate::authorize('view', $teacherUnavailableDate);

        return response()->json(['data' => new TeacherUnavailableDateResource($teacherUnavailableDate->load('teacher:id,public_id,name,email,timezone'))]);
    }

    public function update(Request $request, TeacherUnavailableDate $teacherUnavailableDate): JsonResponse
    {
        Gate::authorize('update', $teacherUnavailableDate);

        $validated = $this->validatePayload($request, false);
        $this->assertTeacherCanManage($request, $validated['teacher_id'] ?? $teacherUnavailableDate->teacher_id);

        return response()->json([
            'data' => new TeacherUnavailableDateResource($this->availabilityService->updateUnavailableDate($teacherUnavailableDate, $validated)->load('teacher:id,public_id,name,email,timezone')),
        ]);
    }

    public function destroy(TeacherUnavailableDate $teacherUnavailableDate): JsonResponse
    {
        Gate::authorize('delete', $teacherUnavailableDate);

        $teacherUnavailableDate->delete();

        return response()->json(status: 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, bool $creating): array
    {
        return $request->validate([
            'teacher_id' => [$creating ? 'required' : 'sometimes', 'integer', 'exists:users,id'],
            'starts_at' => [$creating ? 'required' : 'sometimes', 'date'],
            'ends_at' => [$creating ? 'required' : 'sometimes', 'date'],
            'timezone' => [$creating ? 'required' : 'sometimes', 'string', Rule::in(timezone_identifiers_list())],
            'is_all_day' => ['sometimes', 'boolean'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);
    }

    private function assertTeacherCanManage(Request $request, int $teacherId): void
    {
        if ($request->user()->hasRole('teacher') && ! $request->user()->hasAnyRole(['admin', 'staff']) && $request->user()->id !== $teacherId) {
            throw ValidationException::withMessages([
                'teacher_id' => 'Teachers can only manage their own unavailable dates.',
            ]);
        }
    }
}
