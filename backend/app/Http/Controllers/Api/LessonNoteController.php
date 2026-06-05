<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LessonNotes\StoreLessonNoteRequest;
use App\Http\Requests\LessonNotes\UpdateLessonNoteRequest;
use App\Http\Resources\LessonNotes\LessonNoteRequirementResource;
use App\Http\Resources\LessonNotes\LessonNoteResource;
use App\Models\Lesson;
use App\Models\LessonNote;
use App\Models\LessonRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class LessonNoteController extends Controller
{
    private const MANAGEABLE_LESSON_STATUSES = [
        Lesson::STATUS_COMPLETED,
        Lesson::STATUS_MISSED_BY_STUDENT,
        Lesson::STATUS_MISSED_BY_TEACHER,
    ];

    private const NOTE_FIELDS = [
        'lesson_objective',
        'topics_covered',
        'vocabulary_learned',
        'grammar_focus',
        'pronunciation_issues',
        'student_speaking_confidence_observation',
        'homework_assignment',
        'recommendation_for_next_lesson',
        'internal_note',
    ];

    /**
     * Display a filtered list of lesson note records.
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
        Gate::authorize('viewAny', LessonNote::class);

        $validated = $request->validate([
            'lesson_id' => ['sometimes', 'integer', 'exists:lessons,id'],
            'student_id' => ['sometimes', 'integer', 'exists:users,id'],
            'teacher_id' => ['sometimes', 'integer', 'exists:users,id'],
            'lesson_record_id' => ['sometimes', 'integer', 'exists:lesson_records,id'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json(
            $this->queryForUser($request->user())
                ->when($validated['lesson_id'] ?? null, fn (Builder $query, int $lessonId) => $query->where('lesson_id', $lessonId))
                ->when($validated['student_id'] ?? null, fn (Builder $query, int $studentId) => $query->where('student_id', $studentId))
                ->when($validated['teacher_id'] ?? null, fn (Builder $query, int $teacherId) => $query->where('teacher_id', $teacherId))
                ->when($validated['lesson_record_id'] ?? null, fn (Builder $query, int $lessonRecordId) => $query->where('lesson_record_id', $lessonRecordId))
                ->orderByDesc('submitted_at')
                ->orderByDesc('created_at')
                ->paginate($validated['per_page'] ?? 25)
                ->through(fn (LessonNote $lessonNote) => new LessonNoteResource($lessonNote))
        );
    }

    /**
     * Display pending lesson note records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * Inline validation rejects missing or invalid request data before processing. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON response containing the requested data.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function pending(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', LessonNote::class);

        $validated = $request->validate([
            'student_id' => ['sometimes', 'integer', 'exists:users,id'],
            'teacher_id' => ['sometimes', 'integer', 'exists:users,id'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = $this->missingLessonNoteQueryForUser($request->user())
            ->when($validated['student_id'] ?? null, fn (Builder $query, int $studentId) => $query->where('student_id', $studentId))
            ->when($validated['teacher_id'] ?? null, fn (Builder $query, int $teacherId) => $query->where('teacher_id', $teacherId))
            ->when($validated['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('start_time', '>=', $from))
            ->when($validated['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('start_time', '<=', $to));

        $missingNotesCount = (clone $query)->count();

        $completedLessonsRequiringNotes = $this->lessonNoteRequiredQueryForUser($request->user())
            ->when($validated['student_id'] ?? null, fn (Builder $query, int $studentId) => $query->where('student_id', $studentId))
            ->when($validated['teacher_id'] ?? null, fn (Builder $query, int $teacherId) => $query->where('teacher_id', $teacherId))
            ->when($validated['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('start_time', '>=', $from))
            ->when($validated['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('start_time', '<=', $to))
            ->count();

        return LessonNoteRequirementResource::collection(
            $query
                ->with(['student:id,public_id,name,email,timezone', 'teacher:id,public_id,name,email,timezone'])
                ->orderByDesc('start_time')
                ->paginate($validated['per_page'] ?? 25)
        )->additional([
            'meta' => [
                'pending_notes' => $missingNotesCount,
                'missing_notes' => $missingNotesCount,
                'completed_lessons_requiring_notes' => $completedLessonsRequiringNotes,
                'note_required_statuses' => Lesson::NOTE_REQUIRED_STATUSES,
                'note_not_required_statuses' => Lesson::NOTE_NOT_REQUIRED_STATUSES,
            ],
        ])->response();
    }

    /**
     * Create a new lesson note record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * The StoreLessonNoteRequest handles authorization and validation before the controller action runs. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON payload with the created resource or action result.
     *
     * @param  StoreLessonNoteRequest  $request
     * @return JsonResponse
     */
    public function store(StoreLessonNoteRequest $request): JsonResponse
    {
        Gate::authorize('create', LessonNote::class);

        $payload = $request->validated();
        $lesson = Lesson::findOrFail($payload['lesson_id']);
        $this->assertLessonCanBeNoted($lesson, $request->user());
        $this->assertLessonDoesNotAlreadyHaveNote($lesson);

        $lessonRecord = null;
        if (array_key_exists('lesson_record_id', $payload) && $payload['lesson_record_id'] !== null) {
            $lessonRecord = LessonRecord::findOrFail($payload['lesson_record_id']);
            $this->assertLessonRecordMatchesLesson($lessonRecord, $lesson);
            $this->assertLessonRecordDoesNotAlreadyHaveNote($lessonRecord);
        } else {
            $lessonRecord = $this->matchingLessonRecordForLesson($lesson);
        }

        $lessonNote = LessonNote::create([
            ...Arr::only($payload, self::NOTE_FIELDS),
            'lesson_id' => $lesson->id,
            'student_id' => $lesson->student_id,
            'teacher_id' => $lesson->teacher_id,
            'author_id' => $request->user()->id,
            'lesson_record_id' => $lessonRecord?->id,
            'submitted_at' => now(),
        ]);

        return response()->json([
            'data' => new LessonNoteResource($lessonNote->load($this->relations())),
        ], 201);
    }

    /**
     * Display the selected lesson note record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Route model parameters include $lessonNote.
     * Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON response containing the requested data.
     *
     * @param  LessonNote  $lessonNote
     * @return JsonResponse
     */
    public function show(LessonNote $lessonNote): JsonResponse
    {
        Gate::authorize('view', $lessonNote);

        return response()->json([
            'data' => new LessonNoteResource($lessonNote->load($this->relations())),
        ]);
    }

    /**
     * Update the selected lesson note record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $lessonNote.
     * The UpdateLessonNoteRequest handles authorization and validation before the controller action runs. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON payload with the updated resource or status result.
     *
     * @param  UpdateLessonNoteRequest  $request
     * @param  LessonNote  $lessonNote
     * @return JsonResponse
     */
    public function update(UpdateLessonNoteRequest $request, LessonNote $lessonNote): JsonResponse
    {
        Gate::authorize('update', $lessonNote);

        $payload = $request->validated();

        if (array_key_exists('lesson_record_id', $payload) && $payload['lesson_record_id'] !== null) {
            $lessonRecord = LessonRecord::findOrFail($payload['lesson_record_id']);
            $this->assertLessonRecordMatchesLesson($lessonRecord, $lessonNote->lesson);
            $this->assertLessonRecordDoesNotAlreadyHaveNote($lessonRecord, $lessonNote);
        }

        $lessonNote->fill(Arr::only($payload, [...self::NOTE_FIELDS, 'lesson_record_id']));
        $lessonNote->save();

        return response()->json([
            'data' => new LessonNoteResource($lessonNote->refresh()->load($this->relations())),
        ]);
    }

    /**
     * Handle the by lesson action for lesson note records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $lesson.
     * Inline validation rejects missing or invalid request data before processing. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON response containing the requested data.
     *
     * @param  Request  $request
     * @param  Lesson  $lesson
     * @return JsonResponse
     */
    public function byLesson(Request $request, Lesson $lesson): JsonResponse
    {
        Gate::authorize('viewAny', LessonNote::class);

        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $notes = $this->queryForUser($request->user())
            ->where('lesson_id', $lesson->id)
            ->orderByDesc('submitted_at')
            ->paginate($validated['per_page'] ?? 25);

        return response()->json($notes->through(fn (LessonNote $lessonNote) => new LessonNoteResource($lessonNote)));
    }

    /**
     * Handle the by student action for lesson note records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $student.
     * Inline validation rejects missing or invalid request data before processing. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON response containing the requested data.
     *
     * @param  Request  $request
     * @param  User  $student
     * @return JsonResponse
     */
    public function byStudent(Request $request, User $student): JsonResponse
    {
        Gate::authorize('viewAny', LessonNote::class);

        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $notes = $this->queryForUser($request->user())
            ->where('student_id', $student->id)
            ->orderByDesc('submitted_at')
            ->paginate($validated['per_page'] ?? 25);

        return response()->json($notes->through(fn (LessonNote $lessonNote) => new LessonNoteResource($lessonNote)));
    }

    /**
     * Handle the assert lesson can be noted action for lesson note records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Route model parameters include $lesson, $actor.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @param  Lesson  $lesson
     * @param  User  $actor
     * @return void
     */
    private function assertLessonCanBeNoted(Lesson $lesson, User $actor): void
    {
        if (! $lesson->student?->hasRole('student')) {
            throw ValidationException::withMessages([
                'lesson_id' => 'The lesson must belong to a student user.',
            ]);
        }

        if (! $lesson->teacher?->hasRole('teacher')) {
            throw ValidationException::withMessages([
                'lesson_id' => 'The lesson must be assigned to a teacher user.',
            ]);
        }

        if ($actor->hasRole('teacher') && ! $actor->hasAnyRole(['admin', 'staff']) && (int) $lesson->teacher_id !== (int) $actor->id) {
            throw ValidationException::withMessages([
                'lesson_id' => 'Teachers can only submit notes for their assigned lessons.',
            ]);
        }

        if (! in_array($lesson->status, self::MANAGEABLE_LESSON_STATUSES, true)) {
            throw ValidationException::withMessages([
                'lesson_id' => 'Lesson notes can only be submitted for completed or missed lessons.',
            ]);
        }
    }

    /**
     * Handle the assert lesson record matches lesson action for lesson note records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Route model parameters include $lessonRecord, $lesson.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @param  LessonRecord  $lessonRecord
     * @param  Lesson  $lesson
     * @return void
     */
    private function assertLessonRecordMatchesLesson(LessonRecord $lessonRecord, Lesson $lesson): void
    {
        if ((int) $lessonRecord->student_id !== (int) $lesson->student_id || (int) $lessonRecord->teacher_id !== (int) $lesson->teacher_id) {
            throw ValidationException::withMessages([
                'lesson_record_id' => 'The selected lesson record does not match the lesson student and teacher.',
            ]);
        }

        if (in_array($lessonRecord->lesson_status, [LessonRecord::STATUS_CANCELLED, LessonRecord::STATUS_RESCHEDULED], true)) {
            throw ValidationException::withMessages([
                'lesson_record_id' => 'Cancelled or rescheduled lesson records cannot be linked to lesson notes.',
            ]);
        }
    }

    /**
     * Handle the assert lesson does not already have note action for lesson note records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Route model parameters include $lesson.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @param  Lesson  $lesson
     * @return void
     */
    private function assertLessonDoesNotAlreadyHaveNote(Lesson $lesson): void
    {
        if ($lesson->lessonNote()->exists()) {
            throw ValidationException::withMessages([
                'lesson_id' => 'This lesson already has a lesson note.',
            ]);
        }
    }

    /**
     * Handle the assert lesson record does not already have note action for lesson note records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Route model parameters include $lessonRecord, $currentLessonNote.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @param  LessonRecord  $lessonRecord
     * @param  ?LessonNote  $currentLessonNote
     * @return void
     */
    private function assertLessonRecordDoesNotAlreadyHaveNote(LessonRecord $lessonRecord, ?LessonNote $currentLessonNote = null): void
    {
        $existingLessonNote = $lessonRecord->lessonNote()->first();

        if ($existingLessonNote !== null && (int) $existingLessonNote->id !== (int) $currentLessonNote?->id) {
            throw ValidationException::withMessages([
                'lesson_record_id' => 'This lesson record already has a lesson note.',
            ]);
        }
    }

    /**
     * Handle the matching lesson record for lesson action for lesson note records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Route model parameters include $lesson.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @param  Lesson  $lesson
     * @return ?LessonRecord
     */
    private function matchingLessonRecordForLesson(Lesson $lesson): ?LessonRecord
    {
        if ($lesson->start_time === null || $lesson->end_time === null) {
            return null;
        }

        return LessonRecord::query()
            ->where('student_id', $lesson->student_id)
            ->where('teacher_id', $lesson->teacher_id)
            ->whereDate('scheduled_date', $lesson->start_time->toDateString())
            ->whereIn('start_time', [$lesson->start_time->format('H:i'), $lesson->start_time->format('H:i:s')])
            ->whereIn('end_time', [$lesson->end_time->format('H:i'), $lesson->end_time->format('H:i:s')])
            ->whereNotIn('lesson_status', [LessonRecord::STATUS_CANCELLED, LessonRecord::STATUS_RESCHEDULED])
            ->whereDoesntHave('lessonNote')
            ->first();
    }

    /**
     * @return Builder<LessonNote>
     *
     * @param  User  $user
     */
    private function queryForUser(User $user): Builder
    {
        return LessonNote::query()
            ->with($this->relations())
            ->when($user->hasRole('teacher') && ! $user->hasAnyRole(['admin', 'staff']), fn (Builder $query) => $query->where('teacher_id', $user->id))
            ->when($user->hasRole('student') && ! $user->hasAnyRole(['admin', 'staff']), fn (Builder $query) => $query->where('student_id', $user->id));
    }

    /**
     * @return Builder<Lesson>
     *
     * @param  User  $user
     */
    private function missingLessonNoteQueryForUser(User $user): Builder
    {
        return $this->lessonNoteRequiredQueryForUser($user)
            ->missingLessonNote();
    }

    /**
     * @return Builder<Lesson>
     *
     * @param  User  $user
     */
    private function lessonNoteRequiredQueryForUser(User $user): Builder
    {
        abort_unless(
            $user->hasRole('admin')
                || ($user->hasRole('staff') && $user->can('lesson_notes.view'))
                || $user->hasRole('teacher'),
            403
        );

        return Lesson::query()
            ->requiringLessonNote()
            ->when($user->hasRole('teacher') && ! $user->hasAnyRole(['admin', 'staff']), fn (Builder $query) => $query->where('teacher_id', $user->id));
    }

    /**
     * @return array<int, string>
     */
    private function relations(): array
    {
        return [
            'lesson:id,public_id,status,start_time,end_time',
            'lesson.learningResources',
            'student:id,public_id,name,email,timezone',
            'teacher:id,public_id,name,email,timezone',
            'author:id,public_id,name,email',
            'lessonRecord:id,public_id',
        ];
    }
}
