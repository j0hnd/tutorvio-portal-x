<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ConversationParticipant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ChatUnreadCountService
{
    public function __construct(
        private readonly ChatRealtimeStateService $chatState,
    ) {}

    public function conversationUnreadCount(User $user, Conversation $conversation, ConversationParticipant $participant): int
    {
        if ((int) $participant->user_id !== (int) $user->id || (int) $participant->conversation_id !== (int) $conversation->id) {
            return 0;
        }

        return $this->chatState->rememberConversationUnreadCount(
            $user->public_id,
            $conversation->public_id,
            fn (): int => $this->calculateConversationUnreadCount($user, $participant)
        );
    }

    public function totalUnreadCount(User $user): int
    {
        return $this->chatState->rememberUnreadCount(
            $user->public_id,
            fn (): int => ConversationParticipant::query()
                ->where('user_id', $user->id)
                ->whereNull('archived_at')
                ->whereNull('deleted_at')
                ->with('lastReadMessage:id,conversation_id,created_at')
                ->get()
                ->sum(fn (ConversationParticipant $participant): int => $this->calculateConversationUnreadCount($user, $participant))
        );
    }

    public function forgetForParticipant(ConversationParticipant $participant): void
    {
        $participant->loadMissing([
            'conversation:id,public_id',
            'user:id,public_id',
        ]);

        if ($participant->user?->public_id === null) {
            return;
        }

        $this->chatState->forgetUnreadCount($participant->user->public_id);

        if ($participant->conversation?->public_id !== null) {
            $this->chatState->forgetConversationUnreadCount(
                $participant->user->public_id,
                $participant->conversation->public_id
            );
        }
    }

    public function forgetForConversation(Conversation $conversation): void
    {
        $conversation->participants()
            ->withTrashed()
            ->with('user:id,public_id')
            ->get()
            ->each(fn (ConversationParticipant $participant) => $this->forgetForParticipant($participant));
    }

    public function calculateConversationUnreadCount(User $user, ConversationParticipant $participant): int
    {
        $lastReadMessage = null;

        if ($participant->last_read_message_id !== null) {
            $loadedLastReadMessage = $participant->relationLoaded('lastReadMessage')
                ? $participant->lastReadMessage
                : null;

            $lastReadMessage = $loadedLastReadMessage?->created_at !== null
                ? $loadedLastReadMessage
                : ConversationMessage::query()
                    ->select(['id', 'conversation_id', 'created_at'])
                    ->find($participant->last_read_message_id);
        }

        return ConversationMessage::query()
            ->where('conversation_id', $participant->conversation_id)
            ->where(function (Builder $query) use ($user): void {
                $query
                    ->whereNull('sender_id')
                    ->orWhere('sender_id', '!=', $user->id);
            })
            ->when(
                $lastReadMessage !== null,
                fn (Builder $query) => $query->where(function (Builder $query) use ($participant, $lastReadMessage): void {
                    $query
                        ->where('created_at', '>', $lastReadMessage->created_at)
                        ->orWhere(function (Builder $query) use ($participant, $lastReadMessage): void {
                            $query
                                ->where('created_at', $lastReadMessage->created_at)
                                ->where('id', '>', $participant->last_read_message_id);
                        });
                }),
                fn (Builder $query) => $query->when(
                    $participant->last_read_at !== null,
                    fn (Builder $query) => $query->where('created_at', '>', $participant->last_read_at)
                )
            )
            ->count();
    }
}
