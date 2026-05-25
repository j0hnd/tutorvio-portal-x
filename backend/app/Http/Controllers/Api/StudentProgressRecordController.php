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
