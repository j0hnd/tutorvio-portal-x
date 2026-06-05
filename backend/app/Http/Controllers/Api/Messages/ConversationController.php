<?php

namespace App\Http\Controllers\Api\Messages;

use App\Http\Controllers\Controller;
use App\Http\Resources\Messages\ConversationResource;
use App\Models\Conversation;
use App\Models\CourseProgram;
use App\Models\CourseProgramStudentAssignment;
use App\Models\User;
use App\Support\PublicIdResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ConversationController extends Controller
{
    /**
     * Display a filtered list of conversation records.
     */
    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        $this->assertCanViewConversations($actor);

        $request->merge(PublicIdResolver::resolveFields($request->all(), [
            'student_id' => User::class,
            'teacher_id' => User::class,
            'course_program_id' => CourseProgram::class,
        ]));

        $validated = $request->validate([
            'type' => ['sometimes', 'string', Rule::in(Conversation::TYPES)],
            'status' => ['sometimes', 'string', Rule::in(Conversation::STATUSES)],
            'student_id' => ['sometimes', 'integer', 'exists:users,id'],
            'teacher_id' => ['sometimes', 'integer', 'exists:users,id'],
            'course_program_id' => ['sometimes', 'integer', 'exists:course_programs,id'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json(
            $this->visibleConversationsFor($actor)
                ->with($this->conversationRelations())
                ->when($validated['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
                ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
                ->when($validated['student_id'] ?? null, fn (Builder $query, int $studentId) => $query->where('student_id', $studentId))
                ->when($validated['teacher_id'] ?? null, fn (Builder $query, int $teacherId) => $query->where('teacher_id', $teacherId))
                ->when($validated['course_program_id'] ?? null, fn (Builder $query, int $courseProgramId) => $query->where('course_program_id', $courseProgramId))
                ->orderByDesc('last_message_at')
                ->orderByDesc('id')
                ->paginate($validated['per_page'] ?? 25)
                ->through(fn (Conversation $conversation) => new ConversationResource($conversation))
        );
    }

    /**
     * Display a public-safe conversation detail record.
     */
    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $actor = $request->user();
        $this->assertCanViewConversations($actor);

        return response()->json([
            'data' => new ConversationResource(
                $this->visibleConversationFor($actor, $conversation)
                    ->load($this->conversationRelations())
            ),
        ]);
    }

    /**
     * Create a conversation when the actor is allowed to reach every participant.
     */
    public function store(Request $request): JsonResponse
    {
        $actor = $request->user();
        $this->assertCanAccessMessages($actor);

        $request->merge(PublicIdResolver::resolveFields($request->all(), [
            'recipient_id' => User::class,
            'student_id' => User::class,
            'teacher_id' => User::class,
            'admin_id' => User::class,
            'course_program_id' => CourseProgram::class,
        ]));

        if ($request->has('participant_ids')) {
            $request->merge([
                'participant_ids' => collect($request->input('participant_ids'))
                    ->map(fn (mixed $value) => PublicIdResolver::toKey($value, User::class))
                    ->all(),
            ]);
        }

        $validated = $request->validate([
            'type' => ['sometimes', 'string', Rule::in([
                Conversation::TYPE_STUDENT_TEACHER,
                Conversation::TYPE_TEACHER_ADMIN,
                Conversation::TYPE_ADMIN_STUDENT,
                Conversation::TYPE_GROUP_COURSE,
            ])],
            'recipient_id' => ['sometimes', 'integer', 'exists:users,id'],
            'student_id' => ['sometimes', 'integer', 'exists:users,id'],
            'teacher_id' => ['sometimes', 'integer', 'exists:users,id'],
            'admin_id' => ['sometimes', 'integer', 'exists:users,id'],
            'course_program_id' => ['sometimes', 'integer', 'exists:course_programs,id'],
            'participant_ids' => ['sometimes', 'array', 'max:100'],
            'participant_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'metadata' => ['sometimes', 'array'],
        ]);

        if (($validated['type'] ?? null) === Conversation::TYPE_GROUP_COURSE) {
            $conversation = $this->createCourseGroupConversation($actor, $validated);

            return $this->conversationResponse($conversation, 201);
        }

        [$type, $participants, $attributes] = $this->resolveDirectConversation($actor, $validated);
        $this->assertCanCreateDirectConversation($actor, $type, $participants);

        $existing = $this->activeOneToOneConversation($participants);
        if ($existing !== null) {
            return $this->conversationResponse($existing);
        }

        $conversation = DB::transaction(function () use ($actor, $validated, $type, $participants, $attributes) {
            $conversation = Conversation::query()->create([
                'type' => $type,
                'title' => $validated['title'] ?? null,
                'status' => Conversation::STATUS_ACTIVE,
                'student_id' => $attributes['student_id'] ?? null,
                'teacher_id' => $attributes['teacher_id'] ?? null,
                'created_by' => $actor->id,
                'metadata' => $validated['metadata'] ?? null,
            ]);

            $this->syncParticipants($conversation, $participants);

            return $conversation;
        });

        return $this->conversationResponse($conversation, 201);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createCourseGroupConversation(User $actor, array $payload): Conversation
    {
        if (! $this->canManageConversations($actor)) {
            abort(403);
        }

        if (! isset($payload['course_program_id'])) {
            throw ValidationException::withMessages([
                'course_program_id' => 'A course program is required for course group conversations.',
            ]);
        }

        $courseProgram = CourseProgram::query()->findOrFail($payload['course_program_id']);
        $participantIds = collect($payload['participant_ids'] ?? [])
            ->push($actor->id)
            ->merge(
                CourseProgramStudentAssignment::query()
                    ->active()
                    ->where('course_program_id', $courseProgram->id)
                    ->pluck('student_id')
            )
            ->unique()
            ->values();

        if ($participantIds->count() < 2) {
            throw ValidationException::withMessages([
                'participant_ids' => 'Course group conversations require at least one participant besides the creator.',
            ]);
        }

        $participants = User::query()->whereKey($participantIds)->get();

        if ($participants->count() !== $participantIds->count()) {
            throw ValidationException::withMessages([
                'participant_ids' => 'One or more selected participants could not be found.',
            ]);
        }

        $participants->each(function (User $user): void {
            if (! $user->hasAnyRole(['student', 'teacher', 'admin', 'staff'])) {
                throw ValidationException::withMessages([
                    'participant_ids' => 'Course group participants must be students, teachers, admins, or staff.',
                ]);
            }
        });

        return DB::transaction(function () use ($actor, $payload, $courseProgram, $participants) {
            $conversation = Conversation::query()->create([
                'type' => Conversation::TYPE_GROUP_COURSE,
                'title' => $payload['title'] ?? $courseProgram->title,
                'status' => Conversation::STATUS_ACTIVE,
                'course_program_id' => $courseProgram->id,
                'created_by' => $actor->id,
                'metadata' => $payload['metadata'] ?? null,
            ]);

            $this->syncParticipants($conversation, $participants);

            return $conversation;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: string, 1: Collection<int, User>, 2: array<string, int>}
     */
    private function resolveDirectConversation(User $actor, array $payload): array
    {
        if (isset($payload['recipient_id'])) {
            $recipient = User::query()->with('studentProfile')->findOrFail($payload['recipient_id']);

            return $this->conversationFromPair($actor->loadMissing('studentProfile'), $recipient);
        }

        if (isset($payload['student_id'], $payload['teacher_id'])) {
            $student = User::query()->with('studentProfile')->findOrFail($payload['student_id']);
            $teacher = User::query()->findOrFail($payload['teacher_id']);

            return [
                Conversation::TYPE_STUDENT_TEACHER,
                collect([$student, $teacher]),
                ['student_id' => $student->id, 'teacher_id' => $teacher->id],
            ];
        }

        if (isset($payload['teacher_id'], $payload['admin_id'])) {
            $teacher = User::query()->findOrFail($payload['teacher_id']);
            $admin = User::query()->findOrFail($payload['admin_id']);

            return [
                Conversation::TYPE_TEACHER_ADMIN,
                collect([$teacher, $admin]),
                ['teacher_id' => $teacher->id],
            ];
        }

        if (isset($payload['student_id'], $payload['admin_id'])) {
            $student = User::query()->with('studentProfile')->findOrFail($payload['student_id']);
            $admin = User::query()->findOrFail($payload['admin_id']);

            return [
                Conversation::TYPE_ADMIN_STUDENT,
                collect([$student, $admin]),
                ['student_id' => $student->id],
            ];
        }

        throw ValidationException::withMessages([
            'recipient_id' => 'A recipient is required for one-to-one conversations.',
        ]);
    }

    /**
     * @return array{0: string, 1: Collection<int, User>, 2: array<string, int>}
     */
    private function conversationFromPair(User $actor, User $recipient): array
    {
        if ($actor->hasRole('student') && ! $actor->hasAnyRole(['admin', 'staff'])) {
            $type = $recipient->hasRole('teacher')
                ? Conversation::TYPE_STUDENT_TEACHER
                : Conversation::TYPE_ADMIN_STUDENT;

            return [
                $type,
                collect([$actor, $recipient]),
                [
                    'student_id' => $actor->id,
                    'teacher_id' => $type === Conversation::TYPE_STUDENT_TEACHER ? $recipient->id : null,
                ],
            ];
        }

        if ($actor->hasRole('teacher') && ! $actor->hasAnyRole(['admin', 'staff'])) {
            $type = $recipient->hasRole('student')
                ? Conversation::TYPE_STUDENT_TEACHER
                : Conversation::TYPE_TEACHER_ADMIN;

            return [
                $type,
                collect([$actor, $recipient]),
                [
                    'student_id' => $type === Conversation::TYPE_STUDENT_TEACHER ? $recipient->id : null,
                    'teacher_id' => $actor->id,
                ],
            ];
        }

        if ($actor->hasAnyRole(['admin', 'staff'])) {
            if ($recipient->hasRole('student')) {
                return [Conversation::TYPE_ADMIN_STUDENT, collect([$actor, $recipient]), ['student_id' => $recipient->id]];
            }

            if ($recipient->hasRole('teacher')) {
                return [Conversation::TYPE_TEACHER_ADMIN, collect([$actor, $recipient]), ['teacher_id' => $recipient->id]];
            }
        }

        throw ValidationException::withMessages([
            'recipient_id' => 'The selected recipient cannot be used for this conversation.',
        ]);
    }

    /**
     * @param  Collection<int, User>  $participants
     */
    private function assertCanCreateDirectConversation(User $actor, string $type, Collection $participants): void
    {
        if ($actor->hasRole('staff') && ! $this->canManageConversations($actor)) {
            abort(403);
        }

        $student = $participants->first(fn (User $user) => $user->hasRole('student'));
        $teacher = $participants->first(fn (User $user) => $user->hasRole('teacher'));
        $adminContact = $participants->first(fn (User $user) => $this->isAllowedAdminContact($user));

        if ($type === Conversation::TYPE_STUDENT_TEACHER) {
            if (! $student?->hasRole('student') || ! $teacher?->hasRole('teacher')) {
                throw ValidationException::withMessages([
                    'recipient_id' => 'Student-teacher conversations require a student and a teacher.',
                ]);
            }

            if ($actor->hasRole('student') && ! $actor->hasAnyRole(['admin', 'staff'])) {
                if ((int) $actor->id !== (int) $student->id || (int) $actor->studentProfile?->assigned_teacher_id !== (int) $teacher->id) {
                    throw ValidationException::withMessages([
                        'recipient_id' => 'Students can only start chats with their assigned teacher.',
                    ]);
                }

                return;
            }

            if ($actor->hasRole('teacher') && ! $actor->hasAnyRole(['admin', 'staff'])) {
                if ((int) $actor->id !== (int) $teacher->id || (int) $student->studentProfile?->assigned_teacher_id !== (int) $actor->id) {
                    throw ValidationException::withMessages([
                        'recipient_id' => 'Teachers can only start chats with assigned students.',
                    ]);
                }

                return;
            }

            if (! $this->canManageConversations($actor)) {
                abort(403);
            }

            return;
        }

        if ($type === Conversation::TYPE_ADMIN_STUDENT) {
            if (! $student?->hasRole('student') || $adminContact === null) {
                throw ValidationException::withMessages([
                    'recipient_id' => 'Student-admin conversations require a student and an allowed admin contact.',
                ]);
            }

            if ($actor->hasRole('student') && ! $actor->hasAnyRole(['admin', 'staff'])) {
                if ((int) $actor->id !== (int) $student->id) {
                    throw ValidationException::withMessages([
                        'recipient_id' => 'Students can only start admin chats for themselves.',
                    ]);
                }

                return;
            }

            if (! $this->canManageConversations($actor)) {
                abort(403);
            }

            return;
        }

        if ($type === Conversation::TYPE_TEACHER_ADMIN) {
            if (! $teacher?->hasRole('teacher') || $adminContact === null) {
                throw ValidationException::withMessages([
                    'recipient_id' => 'Teacher-admin conversations require a teacher and an allowed admin contact.',
                ]);
            }

            if ($actor->hasRole('teacher') && ! $actor->hasAnyRole(['admin', 'staff'])) {
                if ((int) $actor->id !== (int) $teacher->id) {
                    throw ValidationException::withMessages([
                        'recipient_id' => 'Teachers can only start admin chats for themselves.',
                    ]);
                }

                return;
            }

            if (! $this->canManageConversations($actor)) {
                abort(403);
            }
        }
    }

    /**
     * @param  Collection<int, User>  $participants
     */
    private function activeOneToOneConversation(Collection $participants): ?Conversation
    {
        $participantIds = $participants->pluck('id')->unique()->values();

        if ($participantIds->count() !== 2) {
            return null;
        }

        return Conversation::query()
            ->where('status', Conversation::STATUS_ACTIVE)
            ->where('type', '!=', Conversation::TYPE_GROUP_COURSE)
            ->whereHas('participants', fn (Builder $query) => $query->where('user_id', $participantIds[0])->whereNull('deleted_at'))
            ->whereHas('participants', fn (Builder $query) => $query->where('user_id', $participantIds[1])->whereNull('deleted_at'))
            ->whereDoesntHave('participants', fn (Builder $query) => $query
                ->whereNotIn('user_id', $participantIds)
                ->whereNull('deleted_at'))
            ->first();
    }

    /**
     * @return Builder<Conversation>
     */
    private function visibleConversationsFor(User $user): Builder
    {
        return Conversation::query()
            ->when(! $this->canViewAllConversations($user), function (Builder $query) use ($user): void {
                $query->whereHas('participants', function (Builder $query) use ($user): void {
                    $query
                        ->where('user_id', $user->id)
                        ->whereNull('archived_at')
                        ->whereNull('deleted_at');
                });
            });
    }

    private function visibleConversationFor(User $user, Conversation $conversation): Conversation
    {
        return $this->visibleConversationsFor($user)
            ->whereKey($conversation->id)
            ->firstOrFail();
    }

    /**
     * @param  Collection<int, User>  $participants
     */
    private function syncParticipants(Conversation $conversation, Collection $participants): void
    {
        $participants->unique('id')->values()->each(function (User $user) use ($conversation): void {
            $roles = $user->roles->pluck('name')->values()->all();
            $role = $this->participantRole($user);

            $conversation->participants()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'participant_role' => $role,
                    'participant_role_snapshot' => $role,
                    'participant_roles_snapshot' => $roles,
                    'joined_at' => now(),
                ]
            );
        });
    }

    private function participantRole(User $user): ?string
    {
        foreach (['student', 'teacher', 'admin', 'staff'] as $role) {
            if ($user->hasRole($role)) {
                return $role;
            }
        }

        return null;
    }

    private function isAllowedAdminContact(User $user): bool
    {
        return $user->hasRole('admin') || ($user->hasRole('staff') && $user->can('messages.manage'));
    }

    private function canManageConversations(User $user): bool
    {
        return $user->can('manage', Conversation::class);
    }

    private function assertCanAccessMessages(User $user): void
    {
        if (! $user->can('create', Conversation::class)) {
            abort(403);
        }
    }

    private function assertCanViewConversations(User $user): void
    {
        if (! $user->can('viewAny', Conversation::class)) {
            abort(403);
        }
    }

    private function canViewAllConversations(User $user): bool
    {
        return $user->can('viewAll', Conversation::class);
    }

    /**
     * @return array<int, string>
     */
    private function conversationRelations(): array
    {
        return [
            'student:id,public_id,name,email',
            'teacher:id,public_id,name,email',
            'courseProgram:id,public_id,title',
            'createdBy:id,public_id,name,email',
            'lastMessageBy:id,public_id,name,email',
            'participants.lastReadMessage:id,public_id',
            'participants.user:id,public_id,name,email',
        ];
    }

    private function conversationResponse(Conversation $conversation, int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => new ConversationResource($conversation->load($this->conversationRelations())),
        ], $status);
    }
}
