<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ClassOversightResource;
use App\Http\Resources\LessonNotes\LessonNoteResource;
use App\Models\CourseProgramStudentAssignment;
use App\Models\Lesson;
use App\Models\LessonNote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClassOversightController extends Controller
{
    /**
     * Display a filtered list of class oversight records.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * Inline validation rejects missing or invalid request data before processing.
     * Returns a JSON response containing the requested data.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'teacher_id' => ['sometimes', 'integer', 'exists:users,id'],
            'teacher' => ['sometimes', 'integer', 'exists:users,id'],
            'student_id' => ['sometimes', 'integer', 'exists:users,id'],
            'student' => ['sometimes', 'integer', 'exists:users,id'],
            'course_program_id' => ['sometimes', 'integer', 'exists:course_programs,id'],
            'course_id' => ['sometimes', 'integer', 'exists:course_programs,id'],
            'course' => ['sometimes', 'integer', 'exists:course_programs,id'],
            'status' => ['sometimes', 'string', Rule::in(Lesson::STATUSES)],
            'class_status' => ['sometimes', 'string', Rule::in(Lesson::STATUSES)],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'include_teacher_note' => ['sometimes', 'boolean'],
        ]);

        $teacherId = $validated['teacher_id'] ?? $validated['teacher'] ?? null;
        $studentId = $validated['student_id'] ?? $validated['student'] ?? null;
        $courseProgramId = $validated['course_program_id'] ?? $validated['course_id'] ?? $validated['course'] ?? null;
        $status = $validated['status'] ?? $validated['class_status'] ?? null;

        return response()->json(
            Lesson::query()
                ->with([
                    'student:id,public_id,name,email,timezone',
                    'teacher:id,public_id,name,email,timezone',
                    'lessonNote.lesson:id,status,start_time,end_time',
                    'lessonNote.lesson.learningResources',
                    'lessonNote.lessonRecord:id,attendance_status,lesson_status',
                    'lessonNote.student:id,public_id,name,email,timezone',
                    'lessonNote.teacher:id,public_id,name,email,timezone',
                    'lessonNote.author:id,public_id,name,email',
                ])
                ->withCount('issueReports')
                ->when($teacherId, fn (Builder $query, int $id) => $query->where('teacher_id', $id))
                ->when($studentId, fn (Builder $query, int $id) => $query->where('student_id', $id))
                ->when($courseProgramId, function (Builder $query, int $id): void {
                    $query->whereHas('student.courseProgramAssignments', fn (Builder $query) => $query
                        ->where('course_program_id', $id)
                        ->where('status', CourseProgramStudentAssignment::STATUS_ACTIVE));
                })
                ->when($status, fn (Builder $query, string $value) => $query->where('status', $value))
                ->when($validated['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('start_time', '>=', $from))
                ->when($validated['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('start_time', '<=', $to))
                ->orderByDesc('start_time')
                ->paginate($validated['per_page'] ?? 25)
                ->through(fn (Lesson $lesson) => new ClassOversightResource($lesson))
        );
    }

    /**
     * Handle the teacher notes action for class oversight records.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Route model parameters include $lesson.
     * The method can return a forbidden response when authorization or ownership checks fail.
     * Returns a JSON response containing the requested data.
     *
     * @param  Lesson  $lesson
     * @return JsonResponse
     */
    public function teacherNotes(Lesson $lesson): JsonResponse
    {
        $lesson->load([
            'lessonNote.lesson:id,status,start_time,end_time',
            'lessonNote.lesson.learningResources',
            'lessonNote.student:id,public_id,name,email,timezone',
            'lessonNote.teacher:id,public_id,name,email,timezone',
            'lessonNote.author:id,public_id,name,email',
        ]);

        abort_if($lesson->lessonNote === null, 404, 'Teacher notes were not found for this class.');

        return response()->json([
            'data' => new LessonNoteResource($lesson->lessonNote),
        ]);
    }

    /**
     * Handle the review teacher note action for class oversight records.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $lessonNote.
     * Inline validation rejects missing or invalid request data before processing.
     * Returns a JSON response containing the requested data.
     *
     * @param  Request  $request
     * @param  LessonNote  $lessonNote
     * @return JsonResponse
     */
    public function reviewTeacherNote(Request $request, LessonNote $lessonNote): JsonResponse
    {
        $validated = $request->validate([
            'review_status' => ['required', 'string', Rule::in(LessonNote::REVIEW_STATUSES)],
            'review_note' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ]);

        $lessonNote->forceFill([
            'review_status' => $validated['review_status'],
            'review_note' => $validated['review_note'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ])->save();

        return response()->json([
            'data' => new LessonNoteResource($lessonNote->refresh()->load([
                'lesson:id,status,start_time,end_time',
                'lesson.learningResources',
                'student:id,name,email,timezone',
                'teacher:id,name,email,timezone',
                'author:id,name,email',
            ])),
        ]);
    }
}
