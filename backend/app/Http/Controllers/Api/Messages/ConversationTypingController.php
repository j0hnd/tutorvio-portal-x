<?php

namespace App\Http\Controllers\Api\Messages;

use App\Events\Messages\ConversationTypingStateChanged;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\User;
use App\Services\ChatRealtimeStateService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ConversationTypingController extends Controller
{
    public function __construct(private readonly ChatRealtimeStateService $chatState) {}

    public function start(Request $request, Conversation $conversation): JsonResponse
    {
        $actor = $request->user();
        $conversation = $this->visibleConversationFor($actor, $conversation);
        $this->assertCanSendTypingState($actor, $conversation);

        $state = $this->chatState->startTyping($conversation->public_id, $actor->public_id, [
            'conversation_id' => $conversation->public_id,
            'user_id' => $actor->public_id,
            'started_at' => now()->toISOString(),
        ]);

        $this->dispatchTypingState($conversation, $actor, true, $state['expires_at']);

        return response()->json([
            'data' => [
                'conversation_id' => $conversation->public_id,
                'user_id' => $actor->public_id,
                'is_typing' => true,
                'expires_at' => $state['expires_at'],
            ],
        ]);
    }

    public function stop(Request $request, Conversation $conversation): JsonResponse
    {
        $actor = $request->user();
        $conversation = $this->visibleConversationFor($actor, $conversation);
        $this->assertCanSendTypingState($actor, $conversation);

        $this->chatState->stopTyping($conversation->public_id, $actor->public_id);

        $this->dispatchTypingState($conversation, $actor, false, null);

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

    private function dispatchTypingState(Conversation $conversation, User $actor, bool $isTyping, ?string $expiresAt): void
    {
        try {
            ConversationTypingStateChanged::dispatch($conversation, $actor, $isTyping, $expiresAt);
        } catch (Throwable $exception) {
            Log::warning('Chat typing broadcast failed; skipping realtime event.', [
                'realtime_area' => 'chat_typing_broadcast',
                'failure_type' => $exception::class,
            ]);
        }
    }
}
