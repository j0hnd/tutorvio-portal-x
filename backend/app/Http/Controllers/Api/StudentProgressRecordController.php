<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StudentProgressRecords\StoreStudentProgressRecordRequest;
use App\Http\Requests\StudentProgressRecords\UpdateStudentProgressRecordRequest;
use App\Http\Resources\StudentProgressRecords\StudentProgressRecordResource;
use App\Models\StudentProgressRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StudentProgressRecordController extends Controller
{
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

    public function show(StudentProgressRecord $studentProgressRecord): JsonResponse
    {
        Gate::authorize('view', $studentProgressRecord);

        return response()->json([
            'data' => new StudentProgressRecordResource($studentProgressRecord->load($this->relations())),
        ]);
    }

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

    public function update(UpdateStudentProgressRecordRequest $request, StudentProgressRecord $studentProgressRecord): JsonResponse
    {
        Gate::authorize('update', $studentProgressRecord);

        $payload = $this->normalizePayload($request->validated());
        $studentId = $payload['student_id'] ?? $studentProgressRecord->student_id;
        $teacherId = $payload['teacher_id'] ?? $studentProgressRecord->teacher_id;

        $this->assertValidStudentAndTeacher($studentId, $teacherId);
        $this->assertTeacherCanManageStudent($request->user(), $studentId, $teacherId);

        $studentProgressRecord->forceFill([
            ...$payload,
            'updated_by' => $request->user()->id,
        ])->save();

        return response()->json([
            'data' => new StudentProgressRecordResource($studentProgressRecord->refresh()->load($this->relations())),
        ]);
    }

    public function destroy(StudentProgressRecord $studentProgressRecord): JsonResponse
    {
        Gate::authorize('delete', $studentProgressRecord);

        $studentProgressRecord->delete();

        return response()->json(status: 204);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload): array
    {
        if (array_key_exists('goal_status', $payload) && ! array_key_exists('progress_status', $payload)) {
            $payload['progress_status'] = $payload['goal_status'];
        }

        unset($payload['goal_status']);

        return $payload;
    }

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

        if ($actor->hasRole('student') && (int) $actor->id === (int) $student->id) {
            return;
        }

        if (! $actor->hasRole('teacher')) {
            abort(403);
        }

        $isAssignedTeacher = (int) $student->studentProfile?->assigned_teacher_id === (int) $actor->id;
        $hasProgressRecordsForTeacher = StudentProgressRecord::query()
            ->where('student_id', $student->id)
            ->where('teacher_id', $actor->id)
            ->exists();

        if (! $isAssignedTeacher && ! $hasProgressRecordsForTeacher) {
            abort(403);
        }
    }

    private function assertTeacherCanManageStudent(User $actor, int $studentId, int $teacherId): void
    {
        if ($actor->hasRole('admin')) {
            return;
        }

        if (! $actor->hasRole('teacher') || (int) $actor->id !== (int) $teacherId) {
            throw ValidationException::withMessages([
                'teacher_id' => 'Teachers can only manage progress records assigned to themselves.',
            ]);
        }

        $student = User::query()->with('studentProfile')->findOrFail($studentId);

        if ((int) $student->studentProfile?->assigned_teacher_id !== (int) $actor->id) {
            throw ValidationException::withMessages([
                'student_id' => 'Teachers can only manage progress records for students assigned to them.',
            ]);
        }
    }

    /**
     * @return Builder<StudentProgressRecord>
     */
    private function queryForUser(User $user): Builder
    {
        return StudentProgressRecord::query()
            ->with($this->relations())
            ->when($user->hasRole('teacher') && ! $user->hasRole('admin'), fn (Builder $query) => $query->where('teacher_id', $user->id));
    }

    /**
     * @return Builder<StudentProgressRecord>
     */
    private function recordsForStudent(User $actor, User $student): Builder
    {
        return $this->queryForUser($actor)
            ->where('student_id', $student->id);
    }

    /**
     * @return array<string, mixed>
     */
    private function studentPayload(User $student): array
    {
        return [
            'id' => $student->id,
            'name' => $student->name,
            'email' => $student->email,
            'timezone' => $student->timezone,
            'current_level' => $student->studentProfile?->current_level,
            'previous_level' => $this->previousLevel($student),
        ];
    }

    private function previousLevel(User $student): ?string
    {
        $previousLevel = $student->studentProfile?->english_level;
        $currentLevel = $student->studentProfile?->current_level;

        return $previousLevel !== $currentLevel ? $previousLevel : null;
    }

    /**
     * @param  Collection<int, StudentProgressRecord>  $records
     * @return array<string, array<string, mixed>|null>
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
     */
    private function skillSummaryPayload(StudentProgressRecord $record, string $skillArea, string $summary): array
    {
        return [
            'skill_area' => $skillArea,
            'summary' => $summary,
            'record_id' => $record->id,
            'recorded_at' => $record->recorded_at,
            'progress_status' => $record->progress_status,
            'teacher_comments' => $record->teacher_comments,
        ];
    }

    /**
     * @param  Collection<int, StudentProgressRecord>  $records
     */
    private function latestRecordValue(Collection $records, string $field): mixed
    {
        return $records->first(fn (StudentProgressRecord $record) => $this->hasDisplayValue($record->{$field}))?->{$field};
    }

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
     * @param  Collection<int, StudentProgressRecord>  $records
     * @return Collection<int, array<string, mixed>>
     */
    private function milestoneAchievements(Collection $records): Collection
    {
        return $records
            ->flatMap(fn (StudentProgressRecord $record) => collect($record->milestone_achievements ?? [])
                ->filter(fn (mixed $achievement) => is_string($achievement) && ! blank($achievement))
                ->map(fn (string $achievement) => [
                    'achievement' => $achievement,
                    'record_id' => $record->id,
                    'recorded_at' => $record->recorded_at,
                ]))
            ->unique('achievement')
            ->values();
    }

    /**
     * @param  Collection<int, StudentProgressRecord>  $records
     * @return Collection<int, array<string, mixed>>
     */
    private function levelMovementHistory(Collection $records): Collection
    {
        return $records
            ->filter(fn (StudentProgressRecord $record) => ! blank($record->level_movement))
            ->map(fn (StudentProgressRecord $record) => [
                'record_id' => $record->id,
                'recorded_at' => $record->recorded_at,
                'level_movement' => $record->level_movement,
                'teacher_comments' => $record->teacher_comments,
            ])
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function timelineRecord(StudentProgressRecord $record): array
    {
        return [
            'id' => $record->id,
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
                    'id' => $record->teacher->id,
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
            'student:id,name,email,timezone',
            'teacher:id,name,email,timezone',
            'createdBy:id,name,email',
            'updatedBy:id,name,email',
        ];
    }
}
