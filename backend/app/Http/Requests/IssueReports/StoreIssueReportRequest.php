<?php

namespace App\Http\Requests\IssueReports;

use App\Models\CourseProgram;
use App\Models\IssueReport;
use App\Models\LearningResource;
use App\Models\Lesson;
use App\Models\Material;
use App\Models\Scheduling\ClassSchedule;
use App\Models\TeacherStudentAssignment;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates create issue report requests.
 *
 * Expected roles: Students and teachers for scoped self-service reports; admin or staff users with issue report creation access.
 * Request-level authorize() documents any additional checks; otherwise route middleware, controller gates, and policies handle access.
 */
class StoreIssueReportRequest extends FormRequest
{
    /**
     * Determine whether the authenticated user can create an issue report.
     *
     * Students and teachers may create scoped self-service reports; admin or
     * staff users must also have issue report creation access.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        if ($user->hasAnyRole(['student', 'teacher'])) {
            return true;
        }

        return ($user->hasRole('admin') || $user->hasRole('staff')) && $user->can('issue_reports.create');
    }

    /**
     * Normalize issue type aliases and default the related student or teacher to the authenticated reporter when applicable.
     */
    protected function prepareForValidation(): void
    {
        $issueType = $this->input('issue_type', $this->input('type'));

        if (is_string($issueType)) {
            $this->merge([
                'issue_type' => $this->normalizeIssueType($issueType),
            ]);
        }

        if ($this->user()?->hasRole('student') && ! $this->filled('related_student_id')) {
            $this->merge([
                'related_student_id' => $this->user()->id,
            ]);
        }

        if ($this->user()?->hasRole('teacher') && ! $this->filled('related_teacher_id')) {
            $this->merge([
                'related_teacher_id' => $this->user()->id,
            ]);
        }
    }

    /**
     * Get validation rules for create issue report requests.
     *
     * Important rules: sometimes rules support partial updates or optional filters; enum rules constrain values to the relevant model constants; exists rules require referenced records to be present; required rules define the minimum payload for creation.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'string'],
            'issue_type' => ['required', 'string', Rule::in(IssueReport::ISSUE_TYPES)],
            'target_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'lesson_id' => ['sometimes', 'nullable', 'integer', 'exists:lessons,id'],
            'class_schedule_id' => ['sometimes', 'nullable', 'integer', 'exists:class_schedules,id'],
            'related_student_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'related_teacher_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'course_program_id' => ['sometimes', 'nullable', 'integer', 'exists:course_programs,id'],
            'material_id' => ['sometimes', 'nullable', 'integer', 'exists:materials,id'],
            'learning_resource_id' => ['sometimes', 'nullable', 'integer', 'exists:learning_resources,id'],
            'priority' => ['sometimes', 'string', Rule::in(IssueReport::PRIORITIES)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:20000'],
        ];
    }

    /**
     * Register after-validation checks for issue-type requirements, linked user roles, and reporter scope.
     *
     * @param  mixed  $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $issueType = (string) $this->input('issue_type');

            $this->validateTypeRequirements($validator, $issueType);

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->validateLinkedUserRoles($validator);
            $this->validateReporterScope($validator);
        });
    }

    /**
     * Return the sanitized payload for create issue report requests.
     *
     * @return array<string, mixed>
     */
    public function issuePayload(): array
    {
        $validated = $this->safe()->except(['type']);

        return [
            ...$validated,
            'status' => IssueReport::STATUS_OPEN,
            'priority' => $validated['priority'] ?? IssueReport::PRIORITY_NORMAL,
            'reporter_id' => $this->user()->id,
        ];
    }

    /**
     * Support validation for create issue report requests.
     */
    private function normalizeIssueType(string $issueType): string
    {
        $normalized = strtolower(trim($issueType));
        $normalized = str_replace(['-', ' '], '_', $normalized);

        return match ($normalized) {
            IssueReport::TYPE_STUDENT_ABSENT => IssueReport::TYPE_STUDENT_ABSENT_ISSUE_FORM,
            IssueReport::TYPE_TEACHER_ABSENT => IssueReport::TYPE_TEACHER_ABSENT_ISSUE_FORM,
            default => $normalized,
        };
    }

    /**
     * Validate a conditional or role-scoped constraint for create issue report requests.
     *
     * @param  mixed  $validator
     */
    private function validateTypeRequirements($validator, string $issueType): void
    {
        if (in_array($issueType, [
            IssueReport::TYPE_STUDENT_CONCERN,
            IssueReport::TYPE_STUDENT_ABSENT_ISSUE_FORM,
        ], true) && ! $this->filled('related_student_id')) {
            $validator->errors()->add('related_student_id', 'A related student is required for this issue type.');
        }

        if (in_array($issueType, [
            IssueReport::TYPE_TEACHER_CONCERN,
            IssueReport::TYPE_TEACHER_ABSENT_ISSUE_FORM,
        ], true) && ! $this->filled('related_teacher_id')) {
            $validator->errors()->add('related_teacher_id', 'A related teacher is required for this issue type.');
        }

        if (in_array($issueType, [
            IssueReport::TYPE_CLASS_INCIDENT,
            IssueReport::TYPE_STUDENT_ABSENT_ISSUE_FORM,
            IssueReport::TYPE_TEACHER_ABSENT_ISSUE_FORM,
        ], true) && ! $this->filled('lesson_id') && ! $this->filled('class_schedule_id')) {
            $validator->errors()->add('lesson_id', 'A lesson or class schedule is required for this issue type.');
        }
    }

    /**
     * Validate a conditional or role-scoped constraint for create issue report requests.
     *
     * @param  mixed  $validator
     */
    private function validateLinkedUserRoles($validator): void
    {
        $student = $this->filled('related_student_id')
            ? User::query()->find($this->integer('related_student_id'))
            : null;
        $teacher = $this->filled('related_teacher_id')
            ? User::query()->find($this->integer('related_teacher_id'))
            : null;

        if ($student && ! $student->hasRole('student')) {
            $validator->errors()->add('related_student_id', 'The related student must be a student user.');
        }

        if ($teacher && ! $teacher->hasRole('teacher')) {
            $validator->errors()->add('related_teacher_id', 'The related teacher must be a teacher user.');
        }
    }

    /**
     * Validate a conditional or role-scoped constraint for create issue report requests.
     *
     * @param  mixed  $validator
     */
    private function validateReporterScope($validator): void
    {
        $user = $this->user();

        if (! $user?->hasAnyRole(['student', 'teacher'])) {
            return;
        }

        if ($this->filled('target_user_id') && (int) $this->input('target_user_id') !== (int) $user->id) {
            $validator->errors()->add('target_user_id', 'You cannot create an issue on behalf of another user.');
        }

        $lesson = $this->filled('lesson_id') ? Lesson::query()->find($this->integer('lesson_id')) : null;
        $classSchedule = $this->filled('class_schedule_id') ? ClassSchedule::query()->find($this->integer('class_schedule_id')) : null;
        $studentId = $this->integer('related_student_id') ?: null;
        $teacherId = $this->integer('related_teacher_id') ?: null;

        if ($user->hasRole('student')) {
            $this->validateStudentScope($validator, $user, $lesson, $classSchedule, $studentId, $teacherId);
        }

        if ($user->hasRole('teacher')) {
            $this->validateTeacherScope($validator, $user, $lesson, $classSchedule, $studentId, $teacherId);
        }
    }

    /**
     * Validate a conditional or role-scoped constraint for create issue report requests.
     *
     * @param  mixed  $validator
     */
    private function validateStudentScope($validator, User $user, ?Lesson $lesson, ?ClassSchedule $classSchedule, ?int $studentId, ?int $teacherId): void
    {
        if ($studentId !== null && $studentId !== (int) $user->id) {
            $validator->errors()->add('related_student_id', 'Students can only create issues related to themselves.');
        }

        if ($lesson && (int) $lesson->student_id !== (int) $user->id) {
            $validator->errors()->add('lesson_id', 'Students can only link their own lessons.');
        }

        if ($classSchedule && (int) $classSchedule->student_id !== (int) $user->id) {
            $validator->errors()->add('class_schedule_id', 'Students can only link their own classes.');
        }

        if ($teacherId !== null && ! $this->studentCanReferenceTeacher($user, $teacherId, $lesson, $classSchedule)) {
            $validator->errors()->add('related_teacher_id', 'Students can only reference teachers assigned to their own lessons or classes.');
        }

        $this->validateStudentCourseAndResourceScope($validator, $user);
    }

    /**
     * Validate a conditional or role-scoped constraint for create issue report requests.
     *
     * @param  mixed  $validator
     */
    private function validateTeacherScope($validator, User $user, ?Lesson $lesson, ?ClassSchedule $classSchedule, ?int $studentId, ?int $teacherId): void
    {
        if ($teacherId !== null && $teacherId !== (int) $user->id) {
            $validator->errors()->add('related_teacher_id', 'Teachers can only create issues related to themselves as the teacher.');
        }

        if ($lesson && (int) $lesson->teacher_id !== (int) $user->id) {
            $validator->errors()->add('lesson_id', 'Teachers can only link their own lessons.');
        }

        if ($classSchedule && (int) $classSchedule->teacher_id !== (int) $user->id) {
            $validator->errors()->add('class_schedule_id', 'Teachers can only link their own classes.');
        }

        if ($studentId !== null && ! $this->teacherCanReferenceStudent($user, $studentId, $lesson, $classSchedule)) {
            $validator->errors()->add('related_student_id', 'Teachers can only reference assigned students or students in their own lessons or classes.');
        }
    }

    /**
     * Support validation for create issue report requests.
     */
    private function studentCanReferenceTeacher(User $student, int $teacherId, ?Lesson $lesson, ?ClassSchedule $classSchedule): bool
    {
        if ($lesson && (int) $lesson->teacher_id === $teacherId) {
            return true;
        }

        if ($classSchedule && (int) $classSchedule->teacher_id === $teacherId) {
            return true;
        }

        return TeacherStudentAssignment::query()
            ->active()
            ->where('student_id', $student->id)
            ->where('teacher_id', $teacherId)
            ->exists();
    }

    /**
     * Support validation for create issue report requests.
     */
    private function teacherCanReferenceStudent(User $teacher, int $studentId, ?Lesson $lesson, ?ClassSchedule $classSchedule): bool
    {
        if ($lesson && (int) $lesson->student_id === $studentId) {
            return true;
        }

        if ($classSchedule && (int) $classSchedule->student_id === $studentId) {
            return true;
        }

        return TeacherStudentAssignment::query()
            ->active()
            ->where('teacher_id', $teacher->id)
            ->where('student_id', $studentId)
            ->exists();
    }

    /**
     * Validate a conditional or role-scoped constraint for create issue report requests.
     *
     * @param  mixed  $validator
     */
    private function validateStudentCourseAndResourceScope($validator, User $student): void
    {
        if ($this->filled('course_program_id') && ! CourseProgram::query()->visibleTo($student)->whereKey($this->integer('course_program_id'))->exists()) {
            $validator->errors()->add('course_program_id', 'Students can only link their own courses.');
        }

        if ($this->filled('learning_resource_id') && ! LearningResource::query()->visibleTo($student)->whereKey($this->integer('learning_resource_id'))->exists()) {
            $validator->errors()->add('learning_resource_id', 'Students can only link visible resources.');
        }

        if ($this->filled('material_id') && ! Material::query()->whereKey($this->integer('material_id'))->whereHas('students', fn ($query) => $query->whereKey($student->id))->exists()) {
            $validator->errors()->add('material_id', 'Students can only link assigned materials.');
        }
    }
}
