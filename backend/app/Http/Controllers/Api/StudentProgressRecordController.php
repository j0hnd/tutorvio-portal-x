<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditActionType;
use App\Enums\AuditModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\StudentProgressRecords\StoreStudentProgressRecordRequest;
use App\Http\Requests\StudentProgressRecords\UpdateStudentProgressRecordRequest;
use App\Http\Resources\StudentProgressRecords\StudentProgressRecordResource;
use App\Models\StudentProgressRecord;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StudentProgressRecordController extends Controller
{
    /**
     * Create the controller with its service dependencies.
     *
     * The framework resolves this constructor before action-specific route
     * middleware, permissions, validation, and authorization are applied.
     *
     * @param  AuditLogService  $auditLogService
     */
    public function __construct(private readonly AuditLogService $auditLogService) {}

    /**
     * Display a filtered list of student progress records.
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
        Gate::authorize('viewAny', StudentProgressRecord::class);

        $validated = $request->validate([
            'student_id' => ['sometimes', 'integer', 'exists:users,id'],
            'teacher_id' => ['sometimes', 'integer', 'exists:users,id'],
            'skill_area' => ['sometimes', 'string', Rule::in(StudentProgressRecord::SKILL_AREAS)],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date'],
            'level_movement' => ['sometimes', 'string', Rule::in(StudentProgressRecord::LEVEL_MOVEMENTS)],
            'progress_status' => ['sometimes', 'string', Rule::in(StudentProgressRecord::STATUSES)],
            'goal_status' => ['sometimes', 'string', Rule::in(StudentProgressRecord::STATUSES)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $from = $validated['date_from'] ?? $validated['from'] ?? null;
        $to = $validated['date_to'] ?? $validated['to'] ?? null;
        $progressStatus = $validated['goal_status'] ?? $validated['progress_status'] ?? null;

        $this->assertFilterUsers($validated['student_id'] ?? null, $validated['teacher_id'] ?? null);
        $this->assertFiltersVisibleToUser($request->user(), $validated['student_id'] ?? null);

        return response()->json(
            $this->queryForUser($request->user())
                ->when($validated['student_id'] ?? null, fn (Builder $query, int $studentId) => $query->where('student_id', $studentId))
                ->when($validated['teacher_id'] ?? null, fn (Builder $query, int $teacherId) => $query->where('teacher_id', $teacherId))
                ->when($validated['skill_area'] ?? null, fn (Builder $query, string $skillArea) => $query->where('skill_area', $skillArea))
                ->when($from, fn (Builder $query, string $date) => $query->where('recorded_at', '>=', date('Y-m-d 00:00:00', strtotime($date))))
                ->when($to, fn (Builder $query, string $date) => $query->where('recorded_at', '<=', date('Y-m-d 23:59:59', strtotime($date))))
                ->when($validated['level_movement'] ?? null, fn (Builder $query, string $levelMovement) => $query->where('level_movement', $levelMovement))
                ->when($progressStatus, fn (Builder $query, string $status) => $query->where('progress_status', $status))
                ->orderByDesc('recorded_at')
                ->orderByDesc('id')
                ->paginate($validated['per_page'] ?? 25)
                ->through(fn (StudentProgressRecord $studentProgressRecord) => new StudentProgressRecordResource($studentProgressRecord))
        );
    }

    /**
     * Create a new student progress record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * The StoreStudentProgressRecordRequest handles authorization and validation before the controller action runs. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON payload with the created resource or action result.
     *
     * @param  StoreStudentProgressRecordRequest  $request
     * @return JsonResponse
     */
    public function store(StoreStudentProgressRecordRequest $request): JsonResponse
    {
        Gate::authorize('create', StudentProgressRecord::class);

        $payload = $this->normalizePayload($request->validated());
        $this->assertValidStudentAndTeacher($payload['student_id'], $payload['teacher_id']);
        $this->assertTeacherCanManageStudent($request->user(), $payload['student_id'], $payload['teacher_id']);

        $studentProgressRecord = StudentProgressRecord::create([
            ...$payload,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'data' => new StudentProgressRecordResource($studentProgressRecord->load($this->relations())),
        ], 201);
    }

    /**
     * Display the selected student progress record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Route model parameters include $studentProgressRecord.
     * Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON response containing the requested data.
     *
     * @param  StudentProgressRecord  $studentProgressRecord
     * @return JsonResponse
     */
    public function show(StudentProgressRecord $studentProgressRecord): JsonResponse
    {
        Gate::authorize('view', $studentProgressRecord);

        return response()->json([
            'data' => new StudentProgressRecordResource($studentProgressRecord->load($this->relations())),
        ]);
    }

    /**
     * Display a summarized student progress record result.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $student.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @param  Request  $request
     * @param  User  $student
     * @return JsonResponse
     */
    public function summary(Request $request, User $student): JsonResponse
    {
        $this->assertStudentVisibleToUser($request->user(), $student);

        $records = $this->recordsForStudent($request->user(), $student)
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->get();

        $student->loadMissing('studentProfile');
        $completedGoals = $this->uniqueValuesFromRecords($records, 'goals_completed');
        $inProgressGoals = $this->uniqueValuesFromRecords($records, 'goals_in_progress')
            ->reject(fn (string $goal) => $completedGoals->contains($goal))
            ->values();

        return response()->json([
            'data' => [
                'student' => $this->studentPayload($student),
                'latest_progress_summary_per_skill_area' => $this->latestSkillSummaries($records),
                'speaking_confidence_rating' => $this->latestRecordValue($records, 'speaking_confidence_rating'),
                'vocabulary_progress' => $this->latestRecordValue($records, 'vocabulary_progress'),
                'grammar_development' => $this->latestRecordValue($records, 'grammar_development'),
                'pronunciation_progress' => $this->latestRecordValue($records, 'pronunciation_progress'),
                'lesson_completion_count' => $this->latestRecordValue($records, 'lesson_completion_count') ?? 0,
                'completed_goals_count' => $completedGoals->count(),
                'in_progress_goals_count' => $inProgressGoals->count(),
                'goals_completed_count' => $completedGoals->count(),
                'goals_in_progress_count' => $inProgressGoals->count(),
                'goals_completed' => $completedGoals,
                'goals_in_progress' => $inProgressGoals,
                'milestone_achievements' => $this->milestoneAchievements($records),
                'current_level' => $student->studentProfile?->current_level,
                'previous_level' => $this->previousLevel($student),
                'level_movement_history' => $this->levelMovementHistory($records),
            ],
        ]);
    }

    /**
     * Display a timeline of student progress record activity.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $student.
     * Inline validation rejects missing or invalid request data before processing.
     * Returns a JSON response containing the requested data.
     *
     * @param  Request  $request
     * @param  User  $student
     * @return JsonResponse
     */
    public function timeline(Request $request, User $student): JsonResponse
    {
        $validated = $request->validate([
            'skill_area' => ['sometimes', 'string', Rule::in(StudentProgressRecord::SKILL_AREAS)],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date'],
        ]);

        $this->assertStudentVisibleToUser($request->user(), $student);

        $from = $validated['date_from'] ?? $validated['from'] ?? null;
        $to = $validated['date_to'] ?? $validated['to'] ?? null;
        $records = $this->recordsForStudent($request->user(), $student)
            ->when($validated['skill_area'] ?? null, fn (Builder $query, string $skillArea) => $query->where('skill_area', $skillArea))
            ->when($from, fn (Builder $query, string $date) => $query->where('recorded_at', '>=', date('Y-m-d 00:00:00', strtotime($date))))
            ->when($to, fn (Builder $query, string $date) => $query->where('recorded_at', '<=', date('Y-m-d 23:59:59', strtotime($date))))
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => [
                'student' => $this->studentPayload($student->loadMissing('studentProfile')),
                'records' => $records->map(fn (StudentProgressRecord $record) => $this->timelineRecord($record))->values(),
            ],
            'meta' => [
                'count' => $records->count(),
                'filters' => [
                    'skill_area' => $validated['skill_area'] ?? null,
                    'date_from' => $from,
                    'date_to' => $to,
                ],
            ],
        ]);
    }

    /**
     * Update the selected student progress record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $studentProgressRecord.
     * The UpdateStudentProgressRecordRequest handles authorization and validation before the controller action runs. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON payload with the updated resource or status result.
     *
     * @param  UpdateStudentProgressRecordRequest  $request
     * @param  StudentProgressRecord  $studentProgressRecord
     * @return JsonResponse
     */
    public function update(UpdateStudentProgressRecordRequest $request, StudentProgressRecord $studentProgressRecord): JsonResponse
    {
        Gate::authorize('update', $studentProgressRecord);

        $before = clone $studentProgressRecord;
        $payload = $this->normalizePayload($request->validated());
        $studentId = $payload['student_id'] ?? $studentProgressRecord->student_id;
        $teacherId = $payload['teacher_id'] ?? $studentProgressRecord->teacher_id;

        $this->assertValidStudentAndTeacher($studentId, $teacherId);
        $this->assertTeacherCanManageStudent($request->user(), $studentId, $teacherId);

        $studentProgressRecord->forceFill([
            ...$payload,
            'updated_by' => $request->user()->id,
        ])->save();
        $studentProgressRecord = $studentProgressRecord->refresh();
        $changedFields = $this->changedProgressFields($before, $studentProgressRecord, $payload);

        if ($changedFields !== []) {
            $this->auditLogService->record(
                actorUserId: $request->user()->id,
                actionType: AuditActionType::STUDENT_UPDATED,
                module: AuditModule::STUDENTS,
                targetEntityType: 'student_progress_record',
                targetEntityId: $studentProgressRecord->id,
                metadata: [
                    'student_id' => $studentProgressRecord->student_id,
                    'teacher_id' => $studentProgressRecord->teacher_id,
                    'changed_fields' => array_fill_keys($changedFields, true),
                    'previous_status' => $before->progress_status,
                    'new_status' => $studentProgressRecord->progress_status,
                ],
            );
        }

        return response()->json([
            'data' => new StudentProgressRecordResource($studentProgressRecord->load($this->relations())),
        ]);
    }

    /**
     * Delete the selected student progress record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Route model parameters include $studentProgressRecord.
     * Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON confirmation after deletion.
     *
     * @param  StudentProgressRecord  $studentProgressRecord
     * @return JsonResponse
     */
    public function destroy(StudentProgressRecord $studentProgressRecord): JsonResponse
    {
        Gate::authorize('delete', $studentProgressRecord);

        $studentProgressRecord->delete();

        return response()->json(status: 204);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @param  array  $payload
     */
    private function normalizePayload(array $payload): array
    {
        if (array_key_exists('goal_status', $payload) && ! array_key_exists('progress_status', $payload)) {
            $payload['progress_status'] = $payload['goal_status'];
        }

        unset($payload['goal_status']);

        return $payload;
    }

    /**
     * Handle the assert valid student and teacher action for student progress records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Route model parameters include $studentId, $teacherId.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @param  int  $studentId
     * @param  int  $teacherId
     * @return void
     */
    private function assertValidStudentAndTeacher(int $studentId, int $teacherId): void
    {
        $student = User::query()->with('studentProfile')->findOrFail($studentId);
        $teacher = User::query()->findOrFail($teacherId);
        $errors = [];

        if (! $student->hasRole('student')) {
            $errors['student_id'] = 'The selected user must be a student.';
        }

        if (! $teacher->hasRole('teacher')) {
            $errors['teacher_id'] = 'The selected user must be a teacher.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Handle the assert filter users action for student progress records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Route model parameters include $studentId, $teacherId.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @param  ?int  $studentId
     * @param  ?int  $teacherId
     * @return void
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
     * Handle the assert filters visible to user action for student progress records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Route model parameters include $actor, $studentId.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @param  User  $actor
     * @param  ?int  $studentId
     * @return void
     */
    private function assertFiltersVisibleToUser(User $actor, ?int $studentId): void
    {
        if ($studentId === null || $actor->hasRole('admin') || ($actor->hasRole('staff') && $actor->can('student_progress_records.view'))) {
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
     * Handle the assert student visible to user action for student progress records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Route model parameters include $actor, $student.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @param  User  $actor
     * @param  User  $student
     * @return void
     */
    private function assertStudentVisibleToUser(User $actor, User $student): void
    {
        $student->loadMissing('studentProfile');

        if (! $student->hasRole('student')) {
            throw ValidationException::withMessages([
                'student' => 'The selected user must be a student.',
            ]);
        }

        if ($actor->hasRole('admin')) {
            return;
        }

        if ($actor->hasRole('staff') && $actor->can('student_progress_records.view')) {
            return;
        }

        if ($actor->hasRole('student') && (int) $actor->id === (int) $student->id) {
            return;
        }

        if (! $actor->hasRole('teacher')) {
            abort(403);
        }

        if ((int) $student->studentProfile?->assigned_teacher_id !== (int) $actor->id) {
            abort(403);
        }
    }

    /**
     * Handle the assert teacher can manage student action for student progress records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Route model parameters include $actor, $studentId, $teacherId.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @param  User  $actor
     * @param  int  $studentId
     * @param  int  $teacherId
     * @return void
     */
    private function assertTeacherCanManageStudent(User $actor, int $studentId, int $teacherId): void
    {
        if ($actor->hasRole('admin')) {
            return;
        }

        if ($actor->hasRole('staff')) {
            return;
        }

        if (! $actor->hasRole('teacher') || (int) $actor->id !== (int) $teacherId) {
            abort(403);
        }

        $student = User::query()->with('studentProfile')->findOrFail($studentId);

        if ((int) $student->studentProfile?->assigned_teacher_id !== (int) $actor->id) {
            abort(403);
        }
    }

    /**
     * @return Builder<StudentProgressRecord>
     *
     * @param  User  $user
     */
    private function queryForUser(User $user): Builder
    {
        return StudentProgressRecord::query()
            ->with($this->relations())
            ->when($user->hasRole('teacher') && ! $user->hasAnyRole(['admin', 'staff']), fn (Builder $query) => $query->whereHas(
                'student.studentProfile',
                fn (Builder $query) => $query->where('assigned_teacher_id', $user->id)
            ))
            ->when($user->hasRole('student') && ! $user->hasAnyRole(['admin', 'staff']), fn (Builder $query) => $query->where('student_id', $user->id));
    }

    /**
     * @return Builder<StudentProgressRecord>
     *
     * @param  User  $actor
     * @param  User  $student
     */
    private function recordsForStudent(User $actor, User $student): Builder
    {
        return $this->queryForUser($actor)
            ->where('student_id', $student->id);
    }

    /**
     * @return array<string, mixed>
     *
     * @param  User  $student
     */
    private function studentPayload(User $student): array
    {
        return [
            'id' => $student->public_id,
            'name' => $student->name,
            'email' => $student->email,
            'timezone' => $student->timezone,
            'current_level' => $student->studentProfile?->current_level,
            'previous_level' => $this->previousLevel($student),
        ];
    }

    /**
     * Handle the previous level action for student progress records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Route model parameters include $student.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @param  User  $student
     * @return ?string
     */
    private function previousLevel(User $student): ?string
    {
        $previousLevel = $student->studentProfile?->english_level;
        $currentLevel = $student->studentProfile?->current_level;

        return $previousLevel !== $currentLevel ? $previousLevel : null;
    }

    /**
     * @param  Collection<int, StudentProgressRecord>  $records
     * @return array<string, array<string, mixed>|null>
     *
     * @param  Collection  $records
     */
    private function latestSkillSummaries(Collection $records): array
    {
        $summaries = array_fill_keys(StudentProgressRecord::SKILL_AREAS, null);

        foreach ($records as $record) {
            foreach ($record->progress_summary_by_skill ?? [] as $skillArea => $summary) {
                if (! array_key_exists($skillArea, $summaries) || $summaries[$skillArea] !== null || blank($summary)) {
                    continue;
                }

                $summaries[$skillArea] = $this->skillSummaryPayload($record, $skillArea, $summary);
            }

            if ($record->skill_area !== null && array_key_exists($record->skill_area, $summaries) && $summaries[$record->skill_area] === null) {
                $summary = $record->progress_summary_by_skill[$record->skill_area] ?? null;

                if (! blank($summary)) {
                    $summaries[$record->skill_area] = $this->skillSummaryPayload($record, $record->skill_area, $summary);
                }
            }
        }

        return $summaries;
    }

    /**
     * @return array<string, mixed>
     *
     * @param  StudentProgressRecord  $record
     * @param  string  $skillArea
     * @param  string  $summary
     */
    private function skillSummaryPayload(StudentProgressRecord $record, string $skillArea, string $summary): array
    {
        return [
            'skill_area' => $skillArea,
            'summary' => $summary,
            'record_id' => $record->public_id,
            'recorded_at' => $record->recorded_at,
            'progress_status' => $record->progress_status,
            'teacher_comments' => $record->teacher_comments,
        ];
    }

    /**
     * @param  Collection<int, StudentProgressRecord>  $records
     *
     * @param  Collection  $records
     * @param  string  $field
     * @return mixed
     */
    private function latestRecordValue(Collection $records, string $field): mixed
    {
        return $records->first(fn (StudentProgressRecord $record) => $this->hasDisplayValue($record->{$field}))?->{$field};
    }

    /**
     * Handle the has display value action for student progress records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Route model parameters include $value.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @param  mixed  $value
     * @return bool
     */
    private function hasDisplayValue(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return ! blank($value);
        }

        if (is_array($value)) {
            return $value !== [];
        }

        return true;
    }

    /**
     * @param  Collection<int, StudentProgressRecord>  $records
     * @return Collection<int, string>
     *
     * @param  Collection  $records
     * @param  string  $field
     */
    private function uniqueValuesFromRecords(Collection $records, string $field): Collection
    {
        return $records
            ->flatMap(fn (StudentProgressRecord $record) => $record->{$field} ?? [])
            ->filter(fn (mixed $value) => is_string($value) && ! blank($value))
            ->unique()
            ->values();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     *
     * @param  StudentProgressRecord  $before
     * @param  StudentProgressRecord  $after
     * @param  array  $payload
     */
    private function changedProgressFields(
        StudentProgressRecord $before,
        StudentProgressRecord $after,
        array $payload
    ): array {
        $changedFields = [];

        foreach (array_keys($payload) as $field) {
            if (! array_key_exists($field, $after->getAttributes())) {
                continue;
            }

            $beforeValue = $before->getAttribute($field);
            $afterValue = $after->getAttribute($field);

            if ($beforeValue != $afterValue) {
                $changedFields[] = $field;
            }
        }

        return array_values(array_unique($changedFields));
    }

    /**
     * @param  Collection<int, StudentProgressRecord>  $records
     * @return Collection<int, array<string, mixed>>
     *
     * @param  Collection  $records
     */
    private function milestoneAchievements(Collection $records): Collection
    {
        return $records
            ->flatMap(fn (StudentProgressRecord $record) => collect($record->milestone_achievements ?? [])
                ->filter(fn (mixed $achievement) => is_string($achievement) && ! blank($achievement))
                ->map(fn (string $achievement) => [
                    'achievement' => $achievement,
                    'record_id' => $record->public_id,
                    'recorded_at' => $record->recorded_at,
                ]))
            ->unique('achievement')
            ->values();
    }

    /**
     * @param  Collection<int, StudentProgressRecord>  $records
     * @return Collection<int, array<string, mixed>>
     *
     * @param  Collection  $records
     */
    private function levelMovementHistory(Collection $records): Collection
    {
        return $records
            ->filter(fn (StudentProgressRecord $record) => ! blank($record->level_movement))
            ->map(fn (StudentProgressRecord $record) => [
                'record_id' => $record->public_id,
                'recorded_at' => $record->recorded_at,
                'level_movement' => $record->level_movement,
                'teacher_comments' => $record->teacher_comments,
            ])
            ->values();
    }

    /**
     * @return array<string, mixed>
     *
     * @param  StudentProgressRecord  $record
     */
    private function timelineRecord(StudentProgressRecord $record): array
    {
        return [
            'id' => $record->public_id,
            'recorded_at' => $record->recorded_at,
            'date' => $record->recorded_at?->toDateString(),
            'skill_area' => $record->skill_area,
            'progress_summary_by_skill' => $record->progress_summary_by_skill ?? [],
            'metrics' => [
                'speaking_confidence_rating' => $record->speaking_confidence_rating,
                'lesson_completion_count' => $record->lesson_completion_count,
                'level_movement' => $record->level_movement,
                'progress_status' => $record->progress_status,
            ],
            'progress' => [
                'vocabulary_progress' => $record->vocabulary_progress,
                'grammar_development' => $record->grammar_development,
                'pronunciation_progress' => $record->pronunciation_progress,
            ],
            'goals' => [
                'completed' => $record->goals_completed ?? [],
                'in_progress' => $record->goals_in_progress ?? [],
                'completed_count' => count($record->goals_completed ?? []),
                'in_progress_count' => count($record->goals_in_progress ?? []),
            ],
            'milestone_achievements' => $record->milestone_achievements ?? [],
            'teacher_comment' => [
                'comment' => $record->teacher_comments,
                'teacher' => $record->relationLoaded('teacher') && $record->teacher !== null ? [
                    'id' => $record->teacher->public_id,
                    'name' => $record->teacher->name,
                    'email' => $record->teacher->email,
                ] : null,
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function relations(): array
    {
        return [
            'student:id,public_id,name,email,timezone',
            'teacher:id,public_id,name,email,timezone',
            'createdBy:id,name,email',
            'updatedBy:id,name,email',
        ];
    }
}
