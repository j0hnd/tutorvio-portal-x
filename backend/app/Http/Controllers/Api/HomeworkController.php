<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Homeworks\ReviewHomeworkRequest;
use App\Http\Requests\Homeworks\StoreHomeworkRequest;
use App\Http\Requests\Homeworks\UpdateHomeworkProgressRequest;
use App\Http\Resources\Homeworks\HomeworkResource;
use App\Models\Homework;
use App\Models\LearningResource;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Notifications\SystemNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class HomeworkController extends Controller
{
    public function __construct(private readonly SystemNotificationService $notificationService) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Homework::class);

        $validated = $request->validate([
            'lesson_id' => ['sometimes', 'integer', 'exists:lessons,id'],
            'student_id' => ['sometimes', 'integer', 'exists:users,id'],
            'teacher_id' => ['sometimes', 'integer', 'exists:users,id'],
            'status' => ['sometimes', 'string', Rule::in(Homework::STATUSES)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json(
            $this->queryForUser($request->user())
                ->when($validated['lesson_id'] ?? null, fn (Builder $query, int $lessonId) => $query->where('lesson_id', $lessonId))
                ->when($validated['student_id'] ?? null, fn (Builder $query, int $studentId) => $query->where('student_id', $studentId))
                ->when($validated['teacher_id'] ?? null, fn (Builder $query, int $teacherId) => $query->where('teacher_id', $teacherId))
                ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
                ->orderByRaw('due_date IS NULL')
                ->orderBy('due_date')
                ->orderByDesc('created_at')
                ->paginate($validated['per_page'] ?? 25)
                ->through(fn (Homework $homework) => new HomeworkResource($homework))
        );
    }

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

        $this->notifyHomeworkAssigned($homework);

        return response()->json([
            'data' => new HomeworkResource($homework->load($this->relations())),
        ], 201);
    }

    public function show(Homework $homework): JsonResponse
    {
        Gate::authorize('view', $homework);

        return response()->json([
            'data' => new HomeworkResource($homework->load($this->relations())),
        ]);
    }

    public function updateProgress(UpdateHomeworkProgressRequest $request, Homework $homework): JsonResponse
    {
        Gate::authorize('updateProgress', $homework);

        $status = $request->validated('status');

        $homework->forceFill([
            'status' => $status,
            'completed_at' => $status === Homework::STATUS_COMPLETED ? now() : null,
        ])->save();

        return response()->json([
            'data' => new HomeworkResource($homework->refresh()->load($this->relations())),
        ]);
    }

    public function review(ReviewHomeworkRequest $request, Homework $homework): JsonResponse
    {
        Gate::authorize('review', $homework);

        $homework->forceFill([
            'status' => Homework::STATUS_REVIEWED,
            'teacher_feedback' => $request->validated('teacher_feedback'),
            'reviewed_at' => now(),
        ])->save();

        return response()->json([
            'data' => new HomeworkResource($homework->refresh()->load($this->relations())),
        ]);
    }

    private function assertStudentUser(User $student): void
    {
        if (! $student->hasRole('student')) {
            throw ValidationException::withMessages([
                'student_id' => 'The selected user must be a student.',
            ]);
        }
    }

    private function notifyHomeworkAssigned(Homework $homework): void
    {
        try {
            $homework->loadMissing(['student:id,name,email,timezone', 'teacher:id,name,email,timezone']);

            $this->notificationService->homeworkReminder(
                $homework->student,
                'Homework assigned: '.$homework->title,
                $homework->due_date
                    ? 'Your homework is due on '.$homework->due_date->format('M j, Y').'.'
                    : 'New homework has been assigned.',
                [
                    'homework_id' => $homework->id,
                    'lesson_id' => $homework->lesson_id,
                    'teacher_id' => $homework->teacher_id,
                ],
                [
                    'email' => true,
                    'sender_id' => $homework->teacher_id,
                    'source_type' => 'homework',
                    'source_id' => $homework->id,
                    'dedupe_key' => 'homework_assigned:'.$homework->id,
                ]
            );
        } catch (Throwable $exception) {
            Log::warning('Homework reminder notification delivery failed.', [
                'homework_id' => $homework->id,
                'student_id' => $homework->student_id,
                'teacher_id' => $homework->teacher_id,
                'failure_type' => $exception::class,
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
     * @return Builder<Homework>
     */
    private function queryForUser(User $user): Builder
    {
        return Homework::query()
            ->with($this->relations())
            ->when($user->hasRole('teacher') && ! $user->hasAnyRole(['admin', 'staff']), fn (Builder $query) => $query->where('teacher_id', $user->id))
            ->when($user->hasRole('student') && ! $user->hasAnyRole(['admin', 'staff']), fn (Builder $query) => $query->where('student_id', $user->id));
    }

    /**
     * @return array<int, string>
     */
    private function relations(): array
    {
        return [
            'lesson:id,public_id,student_id,teacher_id,status,start_time,end_time',
            'student:id,public_id,name,email,timezone',
            'teacher:id,public_id,name,email,timezone',
            'learningResources',
            'learningResources.createdBy:id,public_id,name,email',
        ];
    }
}
