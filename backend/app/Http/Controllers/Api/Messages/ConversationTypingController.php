<?php

namespace App\Http\Controllers\Api\Messages;

use App\Events\Messages\ConversationTypingStateChanged;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ConversationTypingController extends Controller
{
    public function start(Request $request, Conversation $conversation): JsonResponse
    {
        $actor = $request->user();
        $conversation = $this->visibleConversationFor($actor, $conversation);
        $this->assertCanSendTypingState($actor, $conversation);

        $ttl = max(1, (int) config('chat.typing_indicator_ttl_seconds', 10));
        $expiresAt = now()->addSeconds($ttl);

        Cache::put($this->cacheKey($conversation, $actor), [
            'conversation_id' => $conversation->public_id,
            'user_id' => $actor->public_id,
            'started_at' => now()->toISOString(),
            'expires_at' => $expiresAt->toISOString(),
        ], $expiresAt);

        ConversationTypingStateChanged::dispatch(
            $conversation,
            $actor,
            true,
            $expiresAt->toISOString(),
        );

        return response()->json([
            'data' => [
                'conversation_id' => $conversation->public_id,
                'user_id' => $actor->public_id,
                'is_typing' => true,
                'expires_at' => $expiresAt->toISOString(),
            ],
        ]);
    }

    public function stop(Request $request, Conversation $conversation): JsonResponse
    {
        $actor = $request->user();
        $conversation = $this->visibleConversationFor($actor, $conversation);
        $this->assertCanSendTypingState($actor, $conversation);

        Cache::forget($this->cacheKey($conversation, $actor));

        ConversationTypingStateChanged::dispatch($conversation, $actor, false, null);

        return response()->json([
            'data' => [
                'conversation_id' => $conversation->public_id,
                'user_id' => $actor->public_id,
                'is_typing' => false,
                'expires_at' => null,
            ],
        ]);
    }

    private function visibleConversationFor(User $user, Conversation $conversation): Conversation
    {
        return Conversation::query()
            ->whereKey($conversation->id)
            ->when(! $user->can('viewAll', Conversation::class), function (Builder $query) use ($user): void {
                $query->whereHas('participants', function (Builder $query) use ($user): void {
                    $query
                        ->where('user_id', $user->id)
                        ->whereNull('archived_at')
                        ->whereNull('deleted_at');
                });
            })
            ->firstOrFail();
    }

    private function assertCanSendTypingState(User $actor, Conversation $conversation): void
    {
        if ($actor->status !== User::STATUS_ACTIVE) {
            abort(403);
        }

        if (! ($actor->can('messages.view') || $actor->can('messages.manage'))) {
            abort(403);
        }

        if ($conversation->status !== Conversation::STATUS_ACTIVE) {
            abort(403, 'Closed or archived conversations cannot receive typing events.');
        }

        $isActiveParticipant = $conversation->participants()
            ->where('user_id', $actor->id)
            ->whereNull('archived_at')
            ->whereNull('deleted_at')
            ->exists();

        if (! $isActiveParticipant) {
            abort(403);
        }
    }

    private function cacheKey(Conversation $conversation, User $actor): string
    {
        return "conversation_typing:{$conversation->id}:{$actor->id}";
    }
}
