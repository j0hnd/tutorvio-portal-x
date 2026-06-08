<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\ConversationAttachment;
use App\Models\ConversationEscalation;
use App\Models\ConversationMessage;
use App\Models\ConversationMessagePin;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Throwable;

class ChatMessageEventPublisher
{
    public const EVENT_MESSAGE_SENT = 'message_sent';

    public const EVENT_MESSAGE_READ = 'message_read';

    public const EVENT_ATTACHMENT_ADDED = 'attachment_added';

    public const EVENT_MESSAGE_PINNED = 'message_pinned';

    public const EVENT_CONVERSATION_ESCALATED = 'conversation_escalated';

    public const EVENT_CONVERSATION_UPDATED = 'conversation_updated';

    public function publishMessageSent(ConversationMessage $message, User $actor): bool
    {
        $message->loadMissing(['conversation.participants.user:id,public_id,status', 'attachmentRecords', 'sender:id,public_id']);

        return $this->publish($message->conversation, $actor, self::EVENT_MESSAGE_SENT, [
            'message' => $this->messagePayload($message),
        ]);
    }

    /**
     * @param  Collection<int, ConversationAttachment>|array<int, ConversationAttachment>  $attachments
     */
    public function publishAttachmentAdded(ConversationMessage $message, Collection|array $attachments, User $actor): bool
    {
        $message->loadMissing(['conversation.participants.user:id,public_id,status']);
        $attachments = collect($attachments)->values();

        if ($attachments->isEmpty()) {
            return false;
        }

        return $this->publish($message->conversation, $actor, self::EVENT_ATTACHMENT_ADDED, [
            'message_id' => $message->public_id,
            'attachments' => $attachments
                ->map(fn (ConversationAttachment $attachment): array => [
                    'id' => $attachment->public_id,
                    'type' => $attachment->type,
                ])
                ->all(),
            'attachment_count' => $attachments->count(),
        ]);
    }

    public function publishMessageRead(Conversation $conversation, User $actor, ?ConversationMessage $message): bool
    {
        $conversation->loadMissing('participants.user:id,public_id,status');

        return $this->publish($conversation, $actor, self::EVENT_MESSAGE_READ, [
            'last_read_message_id' => $message?->public_id,
            'read_at' => now()->toISOString(),
        ]);
    }

    public function publishMessagePinned(ConversationMessagePin $pin, User $actor): bool
    {
        $pin->loadMissing(['conversation.participants.user:id,public_id,status', 'message:id,public_id,conversation_id']);

        return $this->publish($pin->conversation, $actor, self::EVENT_MESSAGE_PINNED, [
            'message_id' => $pin->message?->public_id,
            'pin_id' => $pin->public_id,
            'pinned_by' => $actor->public_id,
            'pinned_at' => $pin->pinned_at?->toISOString(),
        ]);
    }

    public function publishConversationEscalated(ConversationEscalation $escalation, User $actor): bool
    {
        $escalation->loadMissing(['conversation.participants.user:id,public_id,status', 'message:id,public_id,conversation_id']);

        return $this->publish($escalation->conversation, $actor, self::EVENT_CONVERSATION_ESCALATED, [
            'escalation_id' => $escalation->public_id,
            'message_id' => $escalation->message?->public_id,
            'status' => $escalation->status,
        ]);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function publishConversationUpdated(Conversation $conversation, User $actor, string $changeType, array $changes = []): bool
    {
        $conversation->loadMissing('participants.user:id,public_id,status');

        return $this->publish($conversation, $actor, self::EVENT_CONVERSATION_UPDATED, [
            'change_type' => $changeType,
            'changes' => $changes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function publish(Conversation $conversation, User $actor, string $event, array $payload): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $eventPayload = [
            'id' => (string) Str::uuid(),
            'event' => $event,
            'conversation_id' => $conversation->public_id,
            'actor_id' => $actor->public_id,
            'target_user_ids' => $this->targetUserIds($conversation),
            'payload' => $payload,
            'occurred_at' => now()->toISOString(),
        ];

        try {
            Redis::publish($this->channel(), json_encode($eventPayload, JSON_THROW_ON_ERROR));

            return true;
        } catch (Throwable $exception) {
            Log::warning('Chat event publish failed.', [
                'chat_event' => $event,
                'conversation_id' => $conversation->public_id,
                'failure_type' => $exception::class,
            ]);

            return false;
        }
    }

    /**
     * @return array<int, string>
     */
    private function targetUserIds(Conversation $conversation): array
    {
        return $conversation->participants
            ->filter(fn ($participant): bool => $participant->archived_at === null && $participant->deleted_at === null)
            ->map(fn ($participant): ?string => $participant->user?->status === User::STATUS_ACTIVE ? $participant->user->public_id : null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function messagePayload(ConversationMessage $message): array
    {
        return [
            'id' => $message->public_id,
            'sender_id' => $message->sender?->public_id,
            'status' => $message->status,
            'message_type' => $message->messageType(),
            'has_attachments' => $message->attachmentRecords->isNotEmpty(),
            'attachment_count' => $message->attachmentRecords->count(),
            'created_at' => $message->created_at?->toISOString(),
        ];
    }

    private function channel(): string
    {
        return (string) config('chat.realtime.events.channel', 'tvio:chat:events');
    }

    private function enabled(): bool
    {
        return (bool) config('chat.realtime.events.enabled', config('chat.realtime.enabled', true));
    }
}
