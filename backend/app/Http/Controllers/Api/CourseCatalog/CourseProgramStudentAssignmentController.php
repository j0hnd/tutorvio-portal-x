<?php

namespace App\Http\Controllers\Api\CourseCatalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseCatalog\CourseProgramStudentAssignmentResource;
use App\Models\CourseProgram;
use App\Models\CourseProgramStudentAssignment;
use App\Models\User;
use App\Support\PublicIdResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CourseProgramStudentAssignmentController extends Controller
{
    /**
     * Handle the course students action for course program student assignment records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $courseProgram.
     * Inline validation rejects missing or invalid request data before processing. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON response containing the requested data.
     */
    public function courseStudents(Request $request, CourseProgram $courseProgram): JsonResponse
    {
        Gate::authorize('viewStudentAssignments', $courseProgram);

        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $assignments = $courseProgram->studentAssignments()
            ->active()
            ->with(['courseProgram', 'student:id,public_id,name,email,status', 'assignedBy:id,public_id,name,email'])
            ->orderByDesc('assigned_at')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 25);

        return response()->json(
            $assignments->through(fn (CourseProgramStudentAssignment $assignment) => new CourseProgramStudentAssignmentResource($assignment))
        );
    }

    /**
     * Handle the assign students action for course program student assignment records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $courseProgram.
     * Inline validation rejects missing or invalid request data before processing. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON response containing the requested data.
     */
    public function assignStudents(Request $request, CourseProgram $courseProgram): JsonResponse
    {
        Gate::authorize('update', $courseProgram);
        $request->merge([
            'student_ids' => collect($request->input('student_ids', []))
                ->map(fn (mixed $id) => PublicIdResolver::toKey($id, User::class))
                ->all(),
        ]);

        $validated = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        $studentIds = array_values(array_unique(array_map('intval', $validated['student_ids'])));
        $this->assertStudentUsers($studentIds);
        $this->assertNoActiveAssignments($courseProgram, $studentIds);

        $assignments = DB::transaction(function () use ($courseProgram, $studentIds, $validated, $request) {
            $now = now();

            foreach ($studentIds as $studentId) {
                CourseProgramStudentAssignment::updateOrCreate(
                    [
                        'course_program_id' => $courseProgram->id,
                        'student_id' => $studentId,
                    ],
                    [
                        'assigned_by' => $request->user()->id,
                        'assigned_at' => $now,
                        'status' => CourseProgramStudentAssignment::STATUS_ACTIVE,
                        'start_date' => $validated['start_date'] ?? null,
                        'notes' => $validated['notes'] ?? null,
                    ]
                );
            }

            return $courseProgram->studentAssignments()
                ->whereIn('student_id', $studentIds)
                ->active()
                ->with(['courseProgram', 'student:id,public_id,name,email,status', 'assignedBy:id,public_id,name,email'])
                ->orderBy('student_id')
                ->get();
        });

        return response()->json([
            'data' => CourseProgramStudentAssignmentResource::collection($assignments),
        ], 201);
    }

    /**
     * Handle the remove student action for course program student assignment records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Route model parameters include $courseProgram, $student.
     * Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON response containing the requested data.
     */
    public function removeStudent(CourseProgram $courseProgram, User $student): JsonResponse
    {
        Gate::authorize('update', $courseProgram);
        $this->assertStudentUsers([$student->id]);

        $assignment = $courseProgram->studentAssignments()
            ->where('student_id', $student->id)
            ->active()
            ->first();

        if ($assignment === null) {
            throw ValidationException::withMessages([
                'student_id' => 'This student is not actively assigned to the selected course program.',
            ]);
        }

        $assignment->update([
            'status' => CourseProgramStudentAssignment::STATUS_REMOVED,
        ]);

        return response()->json(status: 204);
    }

    /**
     * Handle the student courses action for course program student assignment records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $student.
     * Inline validation rejects missing or invalid request data before processing. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON response containing the requested data.
     */
    public function studentCourses(Request $request, User $student): JsonResponse
    {
        Gate::authorize('viewAny', CourseProgram::class);
        $this->assertStudentUsers([$student->id]);
        $this->authorizeStudentCourses($request->user(), $student);

        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $assignments = $student->courseProgramAssignments()
            ->active()
            ->with([
                'courseProgram.courseType',
                'courseProgram.learningResources' => fn ($query) => $query
                    ->visibleTo($request->user())
                    ->orderBy('learning_resources.id')
                    ->with('createdBy'),
                'assignedBy:id,public_id,name,email',
            ])
            ->whereHas('courseProgram')
            ->orderByDesc('assigned_at')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 25);

        return response()->json(
            $assignments->through(fn (CourseProgramStudentAssignment $assignment) => new CourseProgramStudentAssignmentResource($assignment))
        );
    }

    /**
     * @param  array<int, int>  $studentIds
     */
    private function assertStudentUsers(array $studentIds): void
    {
        $studentCount = User::query()
            ->whereKey($studentIds)
            ->whereHas('roles', fn (Builder $query) => $query->where('name', 'student'))
            ->count();

        if ($studentCount !== count($studentIds)) {
            throw ValidationException::withMessages([
                'student_ids' => 'All selected users must have the student role.',
            ]);
        }
    }

    /**
     * @param  array<int, int>  $studentIds
     */
    private function assertNoActiveAssignments(CourseProgram $courseProgram, array $studentIds): void
    {
        if ($courseProgram->studentAssignments()->active()->whereIn('student_id', $studentIds)->exists()) {
            throw ValidationException::withMessages([
                'student_ids' => 'One or more selected students already have an active assignment for this course program.',
            ]);
        }
    }

    /**
     * Authorize viewing course assignments for a student.
     *
     * Admins and staff with `course_programs.view` can view any student's
     * courses. Students can view their own courses. Teachers can view courses
     * for students assigned to them. All other access is denied.
     */
    private function authorizeStudentCourses(User $user, User $student): void
    {
        if ($user->hasRole('admin') || ($user->hasRole('staff') && $user->can('course_programs.view'))) {
            return;
        }

        if ($user->hasRole('student') && $user->is($student)) {
            return;
        }

        if ($user->hasRole('teacher') && $student->studentProfile?->assigned_teacher_id === $user->id) {
            return;
        }

        throw new AuthorizationException;
    }
}
