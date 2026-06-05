<?php

namespace App\Http\Controllers\Api\Messages;

use App\Http\Controllers\Controller;
use App\Http\Resources\Messages\ConversationMessageResource;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ConversationParticipant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ConversationMessageController extends Controller
{
    /**
     * Display paginated messages for a visible conversation.
     */
    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        $actor = $request->user();
        $conversation = $this->visibleConversationFor($actor, $conversation);

        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'order' => ['sometimes', 'string', Rule::in(['oldest', 'newest'])],
        ]);

        $order = $validated['order'] ?? 'oldest';

        return response()->json(
            $conversation->messages()
                ->with(['conversation:id,public_id', 'sender:id,public_id,name,email'])
                ->when(
                    $order === 'newest',
                    fn (Builder $query) => $query->orderByDesc('created_at')->orderByDesc('id'),
                    fn (Builder $query) => $query->orderBy('created_at')->orderBy('id')
                )
                ->paginate($validated['per_page'] ?? 50)
                ->through(fn (ConversationMessage $message) => new ConversationMessageResource($message))
        );
    }

    /**
     * Send a message in an active conversation.
     */
    public function store(Request $request, Conversation $conversation): JsonResponse
    {
        $actor = $request->user();
        $conversation = $this->visibleConversationFor($actor, $conversation);
        $this->assertCanSendMessage($actor, $conversation);

        $validated = $request->validate([
            'body' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'links' => ['sometimes', 'array', 'max:20'],
            'links.*' => ['required', 'url', 'max:2048'],
            'attachments' => ['sometimes', 'array', 'max:20'],
            'attachments.*' => ['array'],
            'metadata' => ['sometimes', 'array'],
        ]);

        $body = trim((string) ($validated['body'] ?? ''));
        $attachments = $validated['attachments'] ?? [];

        if ($body === '' && $attachments === []) {
            throw ValidationException::withMessages([
                'body' => 'A message body is required unless files are attached.',
            ]);
        }

        $message = DB::transaction(function () use ($actor, $conversation, $validated, $body) {
            $now = now();

            $message = $conversation->messages()->create([
                'sender_id' => $actor->id,
                'body' => $body !== '' ? $body : null,
                'links' => $validated['links'] ?? null,
                'attachments' => $validated['attachments'] ?? null,
                'status' => ConversationMessage::STATUS_SENT,
                'metadata' => $validated['metadata'] ?? null,
            ]);

            $conversation->forceFill([
                'last_message_at' => $now,
                'last_message_by' => $actor->id,
                'last_message_preview' => $body !== '' ? str($body)->limit(250)->toString() : '[Attachment]',
                'last_message_metadata' => [
                    'message_id' => $message->public_id,
                    'message_type' => 'text',
                    'has_attachments' => ! empty($validated['attachments'] ?? []),
                    'has_links' => ! empty($validated['links'] ?? []),
                ],
            ])->save();

            ConversationParticipant::query()
                ->where('conversation_id', $conversation->id)
                ->where('user_id', $actor->id)
                ->update(['last_read_at' => $now]);

            return $message;
        });

        return response()->json([
            'data' => new ConversationMessageResource($message->load(['conversation:id,public_id', 'sender:id,public_id,name,email'])),
        ], 201);
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

    private function assertCanSendMessage(User $actor, Conversation $conversation): void
    {
        if ($actor->status !== User::STATUS_ACTIVE) {
            abort(403);
        }

        if (! ($actor->can('messages.view') || $actor->can('messages.manage'))) {
            abort(403);
        }

        if ($conversation->status !== Conversation::STATUS_ACTIVE) {
            abort(403, 'Closed or archived conversations cannot receive new messages.');
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
}
