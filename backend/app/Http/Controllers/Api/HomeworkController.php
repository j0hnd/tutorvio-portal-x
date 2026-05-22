<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Homeworks\StoreHomeworkRequest;
use App\Http\Resources\Homeworks\HomeworkResource;
use App\Models\Homework;
use App\Models\LearningResource;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class HomeworkController extends Controller
{
    public function store(StoreHomeworkRequest $request): JsonResponse
    {
        Gate::authorize('create', Homework::class);

        $payload = $request->validated();
        $actor = $request->user();

        $lesson = Lesson::query()->with(['student.studentProfile', 'teacher'])->findOrFail($payload['lesson_id']);
        $student = User::query()->with('studentProfile')->findOrFail($payload['student_id']);

        $this->assertStudentUser($student);
        $this->assertLessonStudentRelationship($lesson, $student);
        $this->assertLessonTeacherUser($lesson);
        $this->assertTeacherCanAssignHomework($actor, $lesson, $student);

        $documentIds = $payload['documents'] ?? [];
        $this->assertDocumentAccessForUser($documentIds, $actor);

        $homework = DB::transaction(function () use ($payload, $lesson, $documentIds, $actor) {
            $homework = Homework::create([
                'lesson_id' => $lesson->id,
                'student_id' => $lesson->student_id,
                'teacher_id' => $lesson->teacher_id,
                'title' => $payload['title'],
                'instructions' => $payload['instructions'] ?? null,
                'due_date' => $payload['due_date'] ?? null,
                'status' => Homework::STATUS_ASSIGNED,
                'attachment_links' => $payload['links'] ?? null,
            ]);

            if ($documentIds !== []) {
                $assignedAt = now();
                $attachPayload = [];

                foreach ($documentIds as $documentId) {
                    $attachPayload[$documentId] = [
                        'assigned_by' => $actor->id,
                        'assigned_at' => $assignedAt,
                    ];
                }

                $homework->learningResources()->attach($attachPayload);
            }

            return $homework;
        });

        return response()->json([
            'data' => new HomeworkResource($homework->load($this->relations())),
        ], 201);
    }

    private function assertStudentUser(User $student): void
    {
        if (! $student->hasRole('student')) {
            throw ValidationException::withMessages([
                'student_id' => 'The selected user must be a student.',
            ]);
        }
    }

    private function assertLessonStudentRelationship(Lesson $lesson, User $student): void
    {
        if ((int) $lesson->student_id !== (int) $student->id) {
            throw ValidationException::withMessages([
                'lesson_id' => 'The selected lesson does not belong to the selected student.',
            ]);
        }
    }

    private function assertLessonTeacherUser(Lesson $lesson): void
    {
        if (! $lesson->teacher?->hasRole('teacher')) {
            throw ValidationException::withMessages([
                'lesson_id' => 'The lesson must be assigned to a teacher user.',
            ]);
        }
    }

    private function assertTeacherCanAssignHomework(User $actor, Lesson $lesson, User $student): void
    {
        if (! $actor->hasRole('teacher') || $actor->hasRole('admin')) {
            return;
        }

        if ((int) $lesson->teacher_id !== (int) $actor->id) {
            throw ValidationException::withMessages([
                'lesson_id' => 'Teachers can only assign homework for their own lessons.',
            ]);
        }

        if ((int) $student->studentProfile?->assigned_teacher_id !== (int) $actor->id) {
            throw ValidationException::withMessages([
                'student_id' => 'Teachers can only assign homework to students assigned to them.',
            ]);
        }
    }

    /**
     * @param  array<int, int>  $documentIds
     */
    private function assertDocumentAccessForUser(array $documentIds, User $actor): void
    {
        if ($documentIds === []) {
            return;
        }

        $distinctDocumentIds = array_values(array_unique($documentIds));

        $existingCount = LearningResource::query()
            ->whereIn('id', $distinctDocumentIds)
            ->count();

        if ($existingCount !== count($distinctDocumentIds)) {
            throw ValidationException::withMessages([
                'documents' => 'One or more selected documents are invalid.',
            ]);
        }

        if ($actor->hasRole('admin')) {
            return;
        }

        $visibleCount = LearningResource::query()
            ->visibleTo($actor)
            ->whereIn('id', $distinctDocumentIds)
            ->count();

        if ($visibleCount !== count($distinctDocumentIds)) {
            throw ValidationException::withMessages([
                'documents' => 'One or more selected documents are not accessible to this teacher.',
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function relations(): array
    {
        return [
            'lesson:id,student_id,teacher_id,status,start_time,end_time',
            'student:id,name,email,timezone',
            'teacher:id,name,email,timezone',
            'learningResources',
            'learningResources.createdBy:id,name,email',
        ];
    }
}
