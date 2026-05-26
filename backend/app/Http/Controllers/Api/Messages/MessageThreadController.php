<?php

namespace App\Http\Controllers\Api\Messages;

use App\Http\Controllers\Controller;
use App\Http\Resources\Messages\MessageResource;
use App\Http\Resources\Messages\MessageThreadResource;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\MessageThreadParticipant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MessageThreadController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->assertCanAccessMessages($request->user());

        $validated = $request->validate([
            'status' => ['sometimes', 'string', 'in:active,closed'],
            'student_id' => ['sometimes', 'integer', 'exists:users,id'],
            'teacher_id' => ['sometimes', 'integer', 'exists:users,id'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json(
            $this->visibleThreadsFor($request->user())
                ->with(['participants.user:id,name,email', 'latestMessage.sender:id,name,email'])
                ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
                ->when($validated['student_id'] ?? null, fn (Builder $query, int $studentId) => $query->where('student_id', $studentId))
                ->when($validated['teacher_id'] ?? null, fn (Builder $query, int $teacherId) => $query->where('teacher_id', $teacherId))
                ->orderByDesc('last_message_at')
                ->orderByDesc('id')
                ->paginate($validated['per_page'] ?? 25)
                ->through(fn (MessageThread $thread) => new MessageThreadResource($thread))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertCanAccessMessages($request->user());

        $validated = $request->validate([
            'recipient_id' => ['sometimes', 'integer', 'exists:users,id'],
            'student_id' => ['sometimes', 'integer', 'exists:users,id'],
            'teacher_id' => ['sometimes', 'integer', 'exists:users,id'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'body' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'metadata' => ['sometimes', 'array'],
        ]);

        [$student, $teacher] = $this->resolveThreadUsers($request->user(), $validated);
        $this->assertCanCreateThread($request->user(), $student, $teacher);

        $thread = DB::transaction(function () use ($request, $validated, $student, $teacher) {
            $now = now();
            $body = trim((string) ($validated['body'] ?? ''));

            $thread = MessageThread::create([
                'title' => $validated['title'] ?? null,
                'thread_type' => MessageThread::TYPE_STUDENT_TEACHER,
                'status' => MessageThread::STATUS_ACTIVE,
                'student_id' => $student->id,
                'teacher_id' => $teacher->id,
                'created_by' => $request->user()->id,
                'last_message_at' => $body !== '' ? $now : null,
                'metadata' => $validated['metadata'] ?? null,
            ]);

            $this->syncParticipant($thread, $student, 'student', $request->user()->is($student) && $body !== '' ? $now : null);
            $this->syncParticipant($thread, $teacher, 'teacher', $request->user()->is($teacher) && $body !== '' ? $now : null);

            if ($body !== '') {
                Message::create([
                    'message_thread_id' => $thread->id,
                    'sender_id' => $request->user()->id,
                    'body' => $body,
                    'message_type' => Message::TYPE_STUDENT_TEACHER_MESSAGE,
                    'sent_at' => $now,
                ]);
            }

            return $thread;
        });

        return response()->json([
            'data' => new MessageThreadResource($thread->load(['participants.user:id,name,email', 'latestMessage.sender:id,name,email'])),
        ], 201);
    }

    public function messages(Request $request, MessageThread $messageThread): JsonResponse
    {
        $this->assertCanAccessMessages($request->user());

        $this->visibleThreadFor($request->user(), $messageThread);

        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json(
            $messageThread->messages()
                ->with('sender:id,name,email')
                ->whereNull('archived_at')
                ->orderBy('sent_at')
                ->orderBy('id')
                ->paginate($validated['per_page'] ?? 50)
                ->through(fn (Message $message) => new MessageResource($message))
        );
    }

    public function send(Request $request, MessageThread $messageThread): JsonResponse
    {
        $this->assertCanAccessMessages($request->user());

        $thread = $this->visibleThreadFor($request->user(), $messageThread);
        $this->assertCanSendMessage($request->user(), $thread);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'metadata' => ['sometimes', 'array'],
        ]);

        $message = DB::transaction(function () use ($request, $thread, $validated) {
            $now = now();

            $message = Message::create([
                'message_thread_id' => $thread->id,
                'sender_id' => $request->user()->id,
                'body' => $validated['body'],
                'message_type' => Message::TYPE_STUDENT_TEACHER_MESSAGE,
                'sent_at' => $now,
                'metadata' => $validated['metadata'] ?? null,
            ]);

            $thread->forceFill(['last_message_at' => $now])->save();

            MessageThreadParticipant::query()
                ->where('message_thread_id', $thread->id)
                ->where('user_id', $request->user()->id)
                ->update(['last_read_at' => $now]);

            return $message;
        });

        return response()->json([
            'data' => new MessageResource($message->load('sender:id,name,email')),
        ], 201);
    }

    public function markRead(Request $request, MessageThread $messageThread): JsonResponse
    {
        $this->assertCanAccessMessages($request->user());

        $thread = $this->visibleThreadFor($request->user(), $messageThread);
        $participant = $thread->participants()->where('user_id', $request->user()->id)->firstOrFail();
        $readAt = now();

        $participant->forceFill(['last_read_at' => $readAt])->save();

        return response()->json([
            'data' => [
                'thread_id' => $thread->id,
                'read_at' => $readAt,
                'unread_count' => 0,
            ],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->assertCanAccessMessages($user);

        $count = MessageThreadParticipant::query()
            ->where('user_id', $user->id)
            ->whereNull('archived_at')
            ->get()
            ->sum(function (MessageThreadParticipant $participant) use ($user) {
                return Message::query()
                    ->where('message_thread_id', $participant->message_thread_id)
                    ->where('sender_id', '!=', $user->id)
                    ->whereNull('archived_at')
                    ->when(
                        $participant->last_read_at !== null,
                        fn (Builder $query) => $query->where('sent_at', '>', $participant->last_read_at)
                    )
                    ->count();
            });

        return response()->json([
            'data' => [
                'unread_count' => $count,
            ],
        ]);
    }

    /**
     * @return Builder<MessageThread>
     */
    private function visibleThreadsFor(User $user): Builder
    {
        return MessageThread::query()
            ->where('thread_type', MessageThread::TYPE_STUDENT_TEACHER)
            ->where('is_archived', false)
            ->when(! $this->canAccessMessages($user), function (Builder $query) {
                $query->whereRaw('0 = 1');
            })
            ->when(! $this->canViewAllThreads($user), function (Builder $query) use ($user) {
                $query->whereHas('participants', function (Builder $query) use ($user) {
                    $query
                        ->where('user_id', $user->id)
                        ->whereNull('archived_at');
                });
            });
    }

    private function visibleThreadFor(User $user, MessageThread $thread): MessageThread
    {
        return $this->visibleThreadsFor($user)
            ->whereKey($thread->id)
            ->with('participants')
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: User, 1: User}
     */
    private function resolveThreadUsers(User $actor, array $payload): array
    {
        if (isset($payload['student_id'], $payload['teacher_id'])) {
            return [
                User::query()->with('studentProfile')->findOrFail($payload['student_id']),
                User::query()->findOrFail($payload['teacher_id']),
            ];
        }

        if (! isset($payload['recipient_id'])) {
            throw ValidationException::withMessages([
                'recipient_id' => 'A recipient is required when student_id and teacher_id are not provided.',
            ]);
        }

        $recipient = User::query()->with('studentProfile')->findOrFail($payload['recipient_id']);

        if ($actor->hasRole('student')) {
            return [$actor->loadMissing('studentProfile'), $recipient];
        }

        if ($actor->hasRole('teacher')) {
            return [$recipient, $actor];
        }

        throw ValidationException::withMessages([
            'student_id' => 'Admin and staff users must provide both student_id and teacher_id.',
        ]);
    }

    private function assertCanCreateThread(User $actor, User $student, User $teacher): void
    {
        if (! $this->canAccessMessages($actor)) {
            abort(403);
        }

        if (! $student->hasRole('student')) {
            throw ValidationException::withMessages([
                'student_id' => 'The selected user must be a student.',
            ]);
        }

        if (! $teacher->hasRole('teacher')) {
            throw ValidationException::withMessages([
                'teacher_id' => 'The selected user must be a teacher.',
            ]);
        }

        if ($actor->hasRole('student') && ! $actor->hasAnyRole(['admin', 'staff'])) {
            if ((int) $actor->id !== (int) $student->id || (int) $actor->studentProfile?->assigned_teacher_id !== (int) $teacher->id) {
                throw ValidationException::withMessages([
                    'recipient_id' => 'Students can only message their assigned teacher.',
                ]);
            }

            return;
        }

        if ($actor->hasRole('teacher') && ! $actor->hasAnyRole(['admin', 'staff'])) {
            if ((int) $actor->id !== (int) $teacher->id || (int) $student->studentProfile?->assigned_teacher_id !== (int) $actor->id) {
                throw ValidationException::withMessages([
                    'recipient_id' => 'Teachers can only message students assigned to them.',
                ]);
            }

            return;
        }

        if (! $this->canManageThreads($actor)) {
            abort(403);
        }
    }

    private function assertCanSendMessage(User $actor, MessageThread $thread): void
    {
        if (! $this->canAccessMessages($actor)) {
            abort(403);
        }

        if ($thread->status !== MessageThread::STATUS_ACTIVE) {
            abort(403, 'Closed message threads cannot receive new messages.');
        }

        if ($this->canManageThreads($actor)) {
            return;
        }

        if (! $thread->participants->contains('user_id', $actor->id)) {
            abort(403);
        }
    }

    private function canManageThreads(User $user): bool
    {
        return $user->hasRole('admin') || $user->can('messages.manage');
    }

    private function canAccessMessages(User $user): bool
    {
        return $user->hasRole('admin') || $user->can('messages.view') || $user->can('messages.manage');
    }

    private function assertCanAccessMessages(User $user): void
    {
        if (! $this->canAccessMessages($user)) {
            abort(403);
        }
    }

    private function canViewAllThreads(User $user): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && ($user->can('messages.view') || $user->can('messages.manage')));
    }

    private function syncParticipant(MessageThread $thread, User $user, string $role, mixed $lastReadAt): void
    {
        MessageThreadParticipant::updateOrCreate(
            [
                'message_thread_id' => $thread->id,
                'user_id' => $user->id,
            ],
            [
                'participant_role' => $role,
                'last_read_at' => $lastReadAt,
            ]
        );
    }
}
