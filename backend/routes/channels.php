<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('conversations.{conversationPublicId}', function (User $user, string $conversationPublicId): bool {
    return Conversation::query()
        ->where('public_id', $conversationPublicId)
        ->whereHas('participants', function (Builder $query) use ($user): void {
            $query
                ->where('user_id', $user->id)
                ->whereNull('archived_at')
                ->whereNull('deleted_at');
        })
        ->exists();
});
