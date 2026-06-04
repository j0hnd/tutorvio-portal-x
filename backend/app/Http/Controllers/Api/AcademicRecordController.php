<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AcademicRecords\StoreAcademicRecordRequest;
use App\Http\Requests\AcademicRecords\UpdateAcademicRecordRequest;
use App\Http\Resources\AcademicRecords\AcademicRecordResource;
use App\Models\AcademicRecord;
use App\Models\CourseProgram;
use App\Models\Lesson;
use App\Models\PortalSetting;
use App\Models\Scheduling\ClassSchedule;
use App\Models\User;
use App\Support\PublicIdResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AcademicRecordController extends Controller
{
    /**
     * Display a filtered list of academic records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * Inline validation rejects missing or invalid request data before processing. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON response containing the requested data.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', AcademicRecord::class);
        $this->assertStudentPortalVisibility($request->user());
        $request->merge(PublicIdResolver::resolveFields($request->all(), [
            'student_id' => User::class,
            'teacher_id' => User::class,
            'course_program_id' => CourseProgram::class,
            'lesson_id' => Lesson::class,
            'class_schedule_id' => ClassSchedule::class,
        ]));

        $validated = $request->validate([
            'student_id' => ['sometimes', 'integer', 'exists:users,id'],
            'teacher_id' => ['sometimes', 'integer', 'exists:users,id'],
            'course_program_id' => ['sometimes', 'integer', 'exists:course_programs,id'],
            'lesson_id' => ['sometimes', 'integer', 'exists:lessons,id'],
            'class_schedule_id' => ['sometimes', 'integer', 'exists:class_schedules,id'],
            'record_type' => ['sometimes', 'string', Rule::in(AcademicRecord::RECORD_TYPES)],
            'status' => ['sometimes', 'string', Rule::in(AcademicRecord::STATUSES)],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $this->assertFilterUsers($validated['student_id'] ?? null, $validated['teacher_id'] ?? null);
        $this->assertFiltersVisibleToUser($request->user(), $validated['student_id'] ?? null);

        $from = $validated['date_from'] ?? $validated['from'] ?? null;
        $to = $validated['date_to'] ?? $validated['to'] ?? null;

        return response()->json(
            $this->queryForUser($request->user())
                ->when($validated['student_id'] ?? null, fn (Builder $query, int $studentId) => $query->where('student_id', $studentId))
                ->when($validated['teacher_id'] ?? null, fn (Builder $query, int $teacherId) => $query->where('teacher_id', $teacherId))
                ->when($validated['course_program_id'] ?? null, fn (Builder $query, int $courseProgramId) => $query->where('course_program_id', $courseProgramId))
                ->when($validated['lesson_id'] ?? null, fn (Builder $query, int $lessonId) => $query->where('lesson_id', $lessonId))
                ->when($validated['class_schedule_id'] ?? null, fn (Builder $query, int $classScheduleId) => $query->where('class_schedule_id', $classScheduleId))
                ->when($validated['record_type'] ?? null, fn (Builder $query, string $recordType) => $query->where('record_type', $recordType))
                ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
                ->when(! array_key_exists('status', $validated), fn (Builder $query) => $query->where('status', AcademicRecord::STATUS_ACTIVE))
                ->when($from, fn (Builder $query, string $date) => $query->where('recorded_on', '>=', $date))
                ->when($to, fn (Builder $query, string $date) => $query->where('recorded_on', '<=', $date))
                ->orderByDesc('recorded_on')
                ->orderByDesc('id')
                ->paginate($validated['per_page'] ?? 25)
                ->through(fn (AcademicRecord $academicRecord) => new AcademicRecordResource($academicRecord))
        );
    }

    /**
     * Create a new academic record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * The StoreAcademicRecordRequest handles authorization and validation before the controller action runs. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON payload with the created resource or action result.
     */
    public function store(StoreAcademicRecordRequest $request): JsonResponse
    {
        Gate::authorize('create', AcademicRecord::class);

        $payload = $this->normalizePayload($request->validated(), defaultStatus: true);
        $this->assertValidLinkedEntities($payload);

        $academicRecord = AcademicRecord::create([
            ...$payload,
            'recorded_by' => $request->user()->id,
        ]);

        return response()->json([
            'data' => new AcademicRecordResource($academicRecord->load($this->relations())),
        ], 201);
    }

    /**
     * Display the selected academic record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $academicRecord.
     * Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON response containing the requested data.
     */
    public function show(Request $request, AcademicRecord $academicRecord): JsonResponse
    {
        Gate::authorize('view', $academicRecord);
        $this->assertStudentPortalVisibility($request->user());

        return response()->json([
            'data' => new AcademicRecordResource($academicRecord->load($this->relations())),
        ]);
    }

    /**
     * Update the selected academic record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $academicRecord.
     * The UpdateAcademicRecordRequest handles authorization and validation before the controller action runs. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON payload with the updated resource or status result.
     */
    public function update(UpdateAcademicRecordRequest $request, AcademicRecord $academicRecord): JsonResponse
    {
        Gate::authorize('update', $academicRecord);

        $payload = $this->normalizePayload($request->validated(), $academicRecord->data ?? []);
        $this->assertValidLinkedEntities([
            ...$academicRecord->only(['student_id', 'teacher_id', 'course_program_id', 'lesson_id', 'class_schedule_id']),
            ...$payload,
        ]);

        $academicRecord->forceFill($payload)->save();

        return response()->json([
            'data' => new AcademicRecordResource($academicRecord->refresh()->load($this->relations())),
        ]);
    }

    /**
     * Archive the selected academic record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $academicRecord.
     * Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON payload with the updated resource or status result.
     */
    public function archive(Request $request, AcademicRecord $academicRecord): JsonResponse
    {
        Gate::authorize('archive', $academicRecord);

        $academicRecord->forceFill([
            'status' => AcademicRecord::STATUS_ARCHIVED,
            'archived_by' => $request->user()->id,
            'archived_at' => now(),
        ])->save();

        return response()->json([
            'data' => new AcademicRecordResource($academicRecord->refresh()->load($this->relations())),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $existingData
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload, array $existingData = [], bool $defaultStatus = false): array
    {
        $data = array_key_exists('data', $payload) ? ($payload['data'] ?? []) : $existingData;
        $dataFields = [
            'student_level',
            'placement_result',
            'course_program_history',
            'attendance_summary',
            'progress_summary',
            'teacher_remarks',
            'certificates',
            'completion_notes',
            'internal_notes',
        ];

        foreach ($dataFields as $field) {
            if (array_key_exists($field, $payload)) {
                $data[$field] = $payload[$field];
                unset($payload[$field]);
            }
        }

        $payload['data'] = $data;

        if ($defaultStatus) {
            $payload['status'] ??= AcademicRecord::STATUS_ACTIVE;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertValidLinkedEntities(array $payload): void
    {
        $student = User::query()->findOrFail($payload['student_id']);
        $teacher = isset($payload['teacher_id']) ? User::query()->findOrFail($payload['teacher_id']) : null;
        $errors = [];

        if (! $student->hasRole('student')) {
            $errors['student_id'] = 'The selected user must be a student.';
        }

        if ($teacher !== null && ! $teacher->hasRole('teacher')) {
            $errors['teacher_id'] = 'The selected user must be a teacher.';
        }

        if (isset($payload['lesson_id'])) {
            $lesson = Lesson::query()->findOrFail($payload['lesson_id']);

            if ((int) $lesson->student_id !== (int) $payload['student_id']) {
                $errors['lesson_id'] = 'The selected lesson must belong to the selected student.';
            }

            if (isset($payload['teacher_id']) && (int) $lesson->teacher_id !== (int) $payload['teacher_id']) {
                $errors['teacher_id'] = 'The selected teacher must match the selected lesson teacher.';
            }
        }

        if (isset($payload['class_schedule_id'])) {
            $classSchedule = ClassSchedule::query()->findOrFail($payload['class_schedule_id']);

            if ((int) $classSchedule->student_id !== (int) $payload['student_id']) {
                $errors['class_schedule_id'] = 'The selected class schedule must belong to the selected student.';
            }

            if (isset($payload['teacher_id']) && (int) $classSchedule->teacher_id !== (int) $payload['teacher_id']) {
                $errors['teacher_id'] = 'The selected teacher must match the selected class schedule teacher.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Handle the assert filter users action for academic records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Route model parameters include $studentId, $teacherId.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     */
    private function assertFilterUsers(?int $studentId, ?int $teacherId): void
    {
        $errors = [];

        if ($studentId !== null && ! User::query()->findOrFail($studentId)->hasRole('student')) {
            $errors['student_id'] = 'The selected user must be a student.';
        }

        if ($teacherId !== null && ! User::query()->findOrFail($teacherId)->hasRole('teacher')) {
            $errors['teacher_id'] = 'The selected user must be a teacher.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Handle the assert filters visible to user action for academic records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Route model parameters include $actor, $studentId.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     */
    private function assertFiltersVisibleToUser(User $actor, ?int $studentId): void
    {
        if ($studentId === null || $actor->hasRole('admin') || ($actor->hasRole('staff') && $actor->can('academic_records.view'))) {
            return;
        }

        if ($actor->hasRole('student')) {
            if ((int) $studentId !== (int) $actor->id) {
                abort(403);
            }

            return;
        }

        if ($actor->hasRole('teacher')) {
            $student = User::query()->with('studentProfile')->findOrFail($studentId);

            if ((int) $student->studentProfile?->assigned_teacher_id !== (int) $actor->id) {
                abort(403);
            }
        }
    }

    /**
     * Handle the assert student portal visibility action for academic records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Route model parameters include $user.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     */
    private function assertStudentPortalVisibility(User $user): void
    {
        if (! $user->hasRole('student') || $this->studentRecordsVisible()) {
            return;
        }

        abort(403);
    }

    /**
     * Handle the student records visible action for academic records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * This action does not require additional request parameters beyond the route context.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     */
    private function studentRecordsVisible(): bool
    {
        $setting = PortalSetting::query()
            ->where('key', 'academic_records.settings')
            ->first();

        return (bool) (($setting?->value['visible_to_students'] ?? true));
    }

    /**
     * @return Builder<AcademicRecord>
     */
    private function queryForUser(User $user): Builder
    {
        return AcademicRecord::query()
            ->with($this->relations())
            ->when($user->hasRole('teacher') && ! $user->hasAnyRole(['admin', 'staff']), fn (Builder $query) => $query->whereHas(
                'student.studentProfile',
                fn (Builder $query) => $query->where('assigned_teacher_id', $user->id)
            ))
            ->when($user->hasRole('student') && ! $user->hasAnyRole(['admin', 'staff']), fn (Builder $query) => $query->where('student_id', $user->id));
    }

    /**
     * @return array<int, string>
     */
    private function relations(): array
    {
        return [
            'student:id,public_id,name,email,timezone',
            'teacher:id,public_id,name,email,timezone',
            'courseProgram:id,public_id,title,slug,placement_level',
            'recordedBy:id,name,email',
            'approvedBy:id,name,email',
            'archivedBy:id,name,email',
        ];
    }
}
