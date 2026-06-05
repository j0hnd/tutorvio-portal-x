<?php

namespace App\Services\Messages;

use App\Models\ChatReminder;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ConversationParticipant;
use App\Models\Homework;
use App\Models\Lesson;
use App\Models\LessonNote;
use App\Models\ScheduleChangeRequest;
use App\Models\Scheduling\ClassSchedule;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChatReminderService
{
    public function upcomingLesson(Conversation $conversation, Lesson|ClassSchedule $lesson, ?CarbonInterface $sentAt = null): ConversationMessage
    {
        $startsAt = $lesson instanceof Lesson ? $lesson->start_time : $lesson->starts_at;
        $title = $lesson instanceof ClassSchedule ? ($lesson->title ?: 'your lesson') : 'your lesson';

        return $this->send(
            $conversation,
            ChatReminder::TYPE_UPCOMING_LESSON,
            $lesson,
            'Reminder: '.$this->startsAtLabel($startsAt, 'Your upcoming lesson').' is coming up.',
            [
                'starts_at' => $startsAt?->toJSON(),
                'title' => $title,
            ],
            $sentAt
        );
    }

    public function homeworkDue(Conversation $conversation, Homework $homework, ?CarbonInterface $sentAt = null): ConversationMessage
    {
        $dueLabel = $homework->due_date?->toDateString();

        return $this->send(
            $conversation,
            ChatReminder::TYPE_HOMEWORK_DUE,
            $homework,
            trim('Reminder: Homework "'.$homework->title.'" is due'.($dueLabel !== null ? ' on '.$dueLabel : '').'.'),
            [
                'homework_id' => $homework->public_id,
                'due_date' => $dueLabel,
                'status' => $homework->status,
            ],
            $sentAt
        );
    }

    public function missedClassFollowUp(Conversation $conversation, Lesson|ClassSchedule $lesson, ?CarbonInterface $sentAt = null): ConversationMessage
    {
        return $this->send(
            $conversation,
            ChatReminder::TYPE_MISSED_CLASS_FOLLOW_UP,
            $lesson,
            'Reminder: This class was marked as missed. Please follow up so the next steps are clear.',
            [
                'status' => $lesson->status,
            ],
            $sentAt
        );
    }

    public function pendingTeacherNote(Conversation $conversation, Lesson|LessonNote $source, ?CarbonInterface $sentAt = null): ConversationMessage
    {
        $lesson = $source instanceof LessonNote ? $source->lesson : $source;

        return $this->send(
            $conversation,
            ChatReminder::TYPE_PENDING_TEACHER_NOTE,
            $source,
            'Reminder: The teacher note for this completed lesson is still pending.',
            [
                'lesson_id' => $lesson?->public_id,
                'submitted_at' => $source instanceof LessonNote ? $source->submitted_at?->toJSON() : null,
            ],
            $sentAt
        );
    }

    public function rescheduleConfirmation(Conversation $conversation, ScheduleChangeRequest $request, ?CarbonInterface $sentAt = null): ConversationMessage
    {
        return $this->send(
            $conversation,
            ChatReminder::TYPE_RESCHEDULE_CONFIRMATION,
            $request,
            'Reminder: Your reschedule request has been confirmed for '.$this->startsAtLabel($request->requested_starts_at, 'the new class time').'.',
            [
                'schedule_change_request_id' => $request->public_id,
                'requested_starts_at' => $request->requested_starts_at?->toJSON(),
                'requested_ends_at' => $request->requested_ends_at?->toJSON(),
                'status' => $request->status,
            ],
            $sentAt
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function send(
        Conversation $conversation,
        string $type,
        Model $source,
        string $body,
        array $metadata = [],
        ?CarbonInterface $sentAt = null
    ): ConversationMessage {
        if (! in_array($type, ChatReminder::TYPES, true)) {
            throw ValidationException::withMessages([
                'type' => 'Unsupported chat reminder type.',
            ]);
        }

        $sentAt ??= now();

        return DB::transaction(function () use ($conversation, $type, $source, $body, $metadata, $sentAt): ConversationMessage {
            $conversation = Conversation::query()
                ->whereKey($conversation->id)
                ->lockForUpdate()
                ->firstOrFail();
            $sourceUserIds = $this->sourceUserIds($source);
            $eligibleRecipientIds = $this->eligibleRecipientIds($conversation, $sourceUserIds);

            if ($eligibleRecipientIds->isEmpty()) {
                throw ValidationException::withMessages([
                    'conversation_id' => 'The conversation has no active reminder recipients for this source.',
                ]);
            }

            $dedupeKey = $this->dedupeKey($conversation, $type, $source);

            $existingReminder = ChatReminder::query()
                ->with('conversationMessage')
                ->where('dedupe_key', $dedupeKey)
                ->first();

            if ($existingReminder?->conversationMessage !== null) {
                return $existingReminder->conversationMessage;
            }

            $message = $conversation->messages()->create([
                'sender_id' => null,
                'body' => $body,
                'links' => null,
                'attachments' => null,
                'status' => ConversationMessage::STATUS_SENT,
                'metadata' => [
                    ...$metadata,
                    'message_type' => ConversationMessage::MESSAGE_TYPE_REMINDER,
                    'is_system_message' => true,
                    'is_reminder_message' => true,
                    'reminder_type' => $type,
                    'source_type' => $source->getMorphClass(),
                    'source_id' => $this->publicSourceId($source),
                    'dedupe_key' => $dedupeKey,
                    'recipient_user_ids' => $eligibleRecipientIds->values()->all(),
                ],
                'created_at' => $sentAt,
                'updated_at' => $sentAt,
            ]);

            $reminderAttributes = [
                'conversation_id' => $conversation->id,
                'conversation_message_id' => $message->id,
                'type' => $type,
                'source_type' => $source->getMorphClass(),
                'source_id' => $source->getKey(),
                'dedupe_key' => $dedupeKey,
                'status' => ChatReminder::STATUS_SENT,
                'recipient_user_ids' => $eligibleRecipientIds->values()->all(),
                'sent_at' => $sentAt,
                'metadata' => $metadata,
            ];

            if ($existingReminder === null) {
                ChatReminder::query()->create($reminderAttributes);
            } else {
                $existingReminder->forceFill($reminderAttributes)->save();
            }

            $conversation->forceFill([
                'last_message_at' => $sentAt,
                'last_message_by' => null,
                'last_message_preview' => str($body)->limit(250)->toString(),
                'last_message_metadata' => [
                    'message_id' => $message->public_id,
                    'message_type' => ConversationMessage::MESSAGE_TYPE_REMINDER,
                    'is_system_message' => true,
                    'is_reminder_message' => true,
                    'reminder_type' => $type,
                    'source_type' => $source->getMorphClass(),
                    'source_id' => $this->publicSourceId($source),
                ],
            ])->save();

            return $message;
        });
    }

    /**
     * @return Collection<int, int>
     */
    private function eligibleRecipientIds(Conversation $conversation, Collection $sourceUserIds): Collection
    {
        if ($conversation->status !== Conversation::STATUS_ACTIVE || $sourceUserIds->isEmpty()) {
            return collect();
        }

        $activeParticipants = ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->whereIn('user_id', $sourceUserIds)
            ->whereNull('archived_at')
            ->whereNull('deleted_at')
            ->get();

        if ($activeParticipants->pluck('user_id')->unique()->count() !== $sourceUserIds->count()) {
            return collect();
        }

        return $activeParticipants
            ->whereNull('muted_at')
            ->pluck('user_id')
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, int>
     */
    private function sourceUserIds(Model $source): Collection
    {
        return collect([
            $source->student_id ?? null,
            $source->teacher_id ?? null,
        ])
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();
    }

    private function dedupeKey(Conversation $conversation, string $type, Model $source): string
    {
        return implode(':', [
            'chat_reminder',
            $conversation->getKey(),
            $type,
            str_replace('\\', '.', $source->getMorphClass()),
            $source->getKey(),
        ]);
    }

    private function publicSourceId(Model $source): string|int|null
    {
        return $source->public_id ?? $source->getKey();
    }

    private function startsAtLabel(?CarbonInterface $startsAt, string $fallback): string
    {
        return $startsAt?->format('M j, Y g:i A T') ?? $fallback;
    }
}
