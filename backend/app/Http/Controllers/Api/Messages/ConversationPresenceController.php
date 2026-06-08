<?php

namespace App\Http\Controllers\Api\Messages;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\User;
use App\Services\ChatRealtimeStateService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConversationPresenceController extends Controller
{
    public function __construct(private readonly ChatRealtimeStateService $chatState) {}

    public function update(Request $request): JsonResponse
    {
        $actor = $request->user();
        $validated = $request->validate([
            'state' => ['sometimes', 'string', Rule::in(['online', 'recently_active'])],
            'metadata' => ['sometimes', 'array'],
        ]);

        $state = $validated['state'] ?? 'online';
        $presence = $state === 'recently_active'
            ? $this->chatState->markRecentlyActive($actor->public_id, $validated['metadata'] ?? [])
            : $this->chatState->markOnline($actor->public_id, $validated['metadata'] ?? []);

        return response()->json([
            'data' => [
                'user_id' => $actor->public_id,
                'state' => $presence['state'],
                'online' => $presence['state'] === 'online',
                'last_active_at' => $presence['last_active_at'] ?? $presence['seen_at'] ?? null,
                'expires_at' => $presence['expires_at'] ?? null,
            ],
        ]);
    }

    public function activeConversation(Request $request, Conversation $conversation): JsonResponse
    {
        $actor = $request->user();
        $conversation = $this->participantConversationFor($actor, $conversation);

        $presence = $this->chatState->markRecentlyActive($actor->public_id);
        $activeConversation = $this->chatState->markActiveConversation($actor->public_id, $conversation->public_id);

        return response()->json([
            'data' => [
                'user_id' => $actor->public_id,
                'conversation_id' => $conversation->public_id,
                'state' => $presence['state'],
                'online' => $presence['state'] === 'online',
                'last_active_at' => $presence['last_active_at'] ?? $presence['seen_at'] ?? null,
                'active_conversation' => [
                    'is_active' => (bool) $activeConversation['stored'],
                    'active_at' => $activeConversation['active_at'],
                    'expires_at' => $activeConversation['expires_at'],
                ],
            ],
        ]);
    }

    private function participantConversationFor(User $user, Conversation $conversation): Conversation
    {
        if (! $user->can('view', $conversation)) {
            abort(403);
        }

        return Conversation::query()
            ->whereKey($conversation->id)
            ->whereHas('participants', function (Builder $query) use ($user): void {
                $query
                    ->where('user_id', $user->id)
                    ->whereNull('archived_at')
                    ->whereNull('deleted_at');
            })
            ->firstOrFail();
    }
}
