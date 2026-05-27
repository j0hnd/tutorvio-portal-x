<?php

namespace App\Services;

use App\Models\StudentProfile;
use App\Models\TeacherStudentAssignment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeacherStudentAssignmentService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function assign(User $student, User $teacher, User $assignedBy, array $attributes = []): TeacherStudentAssignment
    {
        $this->assertAssignableUsers($student, $teacher, $assignedBy);

        return DB::transaction(function () use ($student, $teacher, $assignedBy, $attributes) {
            $activeAssignment = TeacherStudentAssignment::query()
                ->where('student_id', $student->id)
                ->active()
                ->lockForUpdate()
                ->first();

            if ($activeAssignment && (int) $activeAssignment->teacher_id === (int) $teacher->id) {
                if ($this->isIdempotentAssignment($activeAssignment, $attributes)) {
                    return $activeAssignment->load(['student', 'teacher', 'assignedBy']);
                }

                throw ValidationException::withMessages([
                    'teacher_id' => 'The selected teacher is already actively assigned to this student.',
                ]);
            }

            $assignedAt = $attributes['assigned_at'] ?? now();

            if ($activeAssignment) {
                $activeAssignment->update([
                    'status' => TeacherStudentAssignment::STATUS_REASSIGNED,
                    'ended_at' => $assignedAt,
                    'active_student_id' => null,
                    'notes' => $attributes['previous_assignment_notes'] ?? $activeAssignment->notes,
                ]);
            }

            $assignment = TeacherStudentAssignment::create([
                'student_id' => $student->id,
                'teacher_id' => $teacher->id,
                'assigned_by' => $assignedBy->id,
                'assigned_at' => $assignedAt,
                'status' => TeacherStudentAssignment::STATUS_ACTIVE,
                'reason' => $attributes['reason'] ?? null,
                'notes' => $attributes['notes'] ?? null,
                'active_student_id' => $student->id,
            ]);

            StudentProfile::query()->updateOrCreate(
                ['user_id' => $student->id],
                ['assigned_teacher_id' => $teacher->id]
            );

            return $assignment->load(['student', 'teacher', 'assignedBy']);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateStatus(TeacherStudentAssignment $assignment, string $status, array $attributes = []): TeacherStudentAssignment
    {
        if (! in_array($status, TeacherStudentAssignment::STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => 'The selected assignment status is invalid.',
            ]);
        }

        return DB::transaction(function () use ($assignment, $status, $attributes) {
            $assignment = TeacherStudentAssignment::query()
                ->whereKey($assignment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($status === TeacherStudentAssignment::STATUS_ACTIVE) {
                $this->assertActiveStudentAndTeacher($assignment->student, $assignment->teacher);

                $conflictingActiveAssignment = TeacherStudentAssignment::query()
                    ->where('student_id', $assignment->student_id)
                    ->active()
                    ->whereKeyNot($assignment->id)
                    ->exists();

                if ($conflictingActiveAssignment) {
                    throw ValidationException::withMessages([
                        'status' => 'This student already has an active teacher assignment.',
                    ]);
                }
            }

            $assignment->fill([
                'status' => $status,
                'reason' => array_key_exists('reason', $attributes) ? $attributes['reason'] : $assignment->reason,
                'notes' => array_key_exists('notes', $attributes) ? $attributes['notes'] : $assignment->notes,
                'ended_at' => $status === TeacherStudentAssignment::STATUS_ACTIVE
                    ? null
                    : ($attributes['ended_at'] ?? $assignment->ended_at ?? now()),
                'active_student_id' => $status === TeacherStudentAssignment::STATUS_ACTIVE ? $assignment->student_id : null,
            ])->save();

            $activeTeacherId = TeacherStudentAssignment::query()
                ->where('student_id', $assignment->student_id)
                ->active()
                ->value('teacher_id');

            StudentProfile::query()->updateOrCreate(
                ['user_id' => $assignment->student_id],
                ['assigned_teacher_id' => $activeTeacherId]
            );

            return $assignment->refresh()->load(['student', 'teacher', 'assignedBy']);
        });
    }

    private function assertAssignableUsers(User $student, User $teacher, User $assignedBy): void
    {
        $errors = [];

        $errors = $this->activeStudentAndTeacherErrors($student, $teacher, $student->id);

        if ($assignedBy->status !== User::STATUS_ACTIVE) {
            $errors['assigned_by'] = 'Assignments can only be created by an active user.';
        }

        if ($assignedBy->hasRole('teacher') && (int) $assignedBy->id === (int) $teacher->id) {
            $errors['teacher_id'] = 'Teachers cannot assign students to themselves.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function assertActiveStudentAndTeacher(User $student, User $teacher): void
    {
        $errors = $this->activeStudentAndTeacherErrors($student, $teacher, $student->id);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @return array<string, string>
     */
    private function activeStudentAndTeacherErrors(User $student, User $teacher, ?int $exceptStudentId = null): array
    {
        $errors = [];

        if ($student->status !== User::STATUS_ACTIVE || ! $student->hasRole('student')) {
            $errors['student_id'] = 'The selected student must be an active student.';
        }

        if ($teacher->status !== User::STATUS_ACTIVE || ! $teacher->hasRole('teacher')) {
            $errors['teacher_id'] = 'The selected teacher must be an active teacher.';
        } elseif ($teacher->teacherProfile?->internal_status !== null
            && ! in_array($teacher->teacherProfile->internal_status, ['available', 'active'], true)) {
            $errors['teacher_id'] = 'The selected teacher is currently unavailable.';
        } elseif ($teacher->teacherProfile?->class_load !== null
            && TeacherStudentAssignment::query()
                ->where('teacher_id', $teacher->id)
                ->active()
                ->when($exceptStudentId, fn ($query) => $query->where('student_id', '!=', $exceptStudentId))
                ->count() >= $teacher->teacherProfile->class_load) {
            $errors['teacher_id'] = 'The selected teacher has reached assignment capacity.';
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function isIdempotentAssignment(TeacherStudentAssignment $assignment, array $attributes): bool
    {
        foreach (['reason', 'notes'] as $attribute) {
            if (array_key_exists($attribute, $attributes) && $attributes[$attribute] !== $assignment->{$attribute}) {
                return false;
            }
        }

        if (array_key_exists('assigned_at', $attributes)
            && $assignment->assigned_at?->ne(Carbon::parse($attributes['assigned_at']))) {
            return false;
        }

        return true;
    }
}
