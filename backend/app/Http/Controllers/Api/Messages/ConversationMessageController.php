<?php

namespace App\Http\Controllers\Api\Messages;

use App\Http\Controllers\Controller;
use App\Http\Resources\Messages\ConversationMessageResource;
use App\Models\Conversation;
use App\Models\ConversationAttachment;
use App\Models\ConversationMessage;
use App\Models\ConversationParticipant;
use App\Models\User;
use App\Services\ConversationAttachmentStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ConversationMessageController extends Controller
{
    public function __construct(private readonly ConversationAttachmentStorage $storage) {}

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
                ->with(['attachmentRecords', 'conversation:id,public_id', 'sender:id,public_id,name,email'])
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
            'attachment_links' => ['sometimes', 'array', 'max:'.(int) config('chat_attachments.max_links_per_message', 20)],
            'attachment_links.*.url' => ['required', 'url', 'max:2048'],
            'attachment_links.*.title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'files' => ['sometimes', 'array', 'max:'.(int) config('chat_attachments.max_files_per_message', 5)],
            'files.*' => [
                'required',
                'file',
                'max:'.(int) config('chat_attachments.max_upload_kilobytes', 10240),
                'mimes:'.implode(',', config('chat_attachments.allowed_extensions', [])),
                'mimetypes:'.implode(',', config('chat_attachments.allowed_mime_types', [])),
                'extensions:'.implode(',', config('chat_attachments.allowed_extensions', [])),
            ],
            'metadata' => ['sometimes', 'array'],
        ]);

        $body = trim((string) ($validated['body'] ?? ''));
        $uploadedFiles = $request->file('files', []);
        $attachmentLinks = $this->normalizedAttachmentLinks($validated);

        if ($body === '' && $uploadedFiles === [] && $attachmentLinks === []) {
            throw ValidationException::withMessages([
                'body' => 'A message body is required unless files are attached.',
            ]);
        }

        $storedFiles = collect($uploadedFiles)
            ->map(fn ($file) => $this->storage->store($file))
            ->all();

        $message = DB::transaction(function () use ($actor, $conversation, $validated, $body, $storedFiles, $attachmentLinks) {
            $now = now();
            $linkUrls = collect($attachmentLinks)->pluck('url')->merge($validated['links'] ?? [])->unique()->values()->all();

            $message = $conversation->messages()->create([
                'sender_id' => $actor->id,
                'body' => $body !== '' ? $body : null,
                'links' => $linkUrls !== [] ? $linkUrls : null,
                'attachments' => null,
                'status' => ConversationMessage::STATUS_SENT,
                'metadata' => $validated['metadata'] ?? null,
            ]);

            foreach ($storedFiles as $storedFile) {
                $message->attachmentRecords()->create($storedFile + [
                    'conversation_id' => $conversation->id,
                    'uploaded_by' => $actor->id,
                    'type' => ConversationAttachment::TYPE_FILE,
                    'title' => $storedFile['original_filename'],
                ]);
            }

            foreach ($attachmentLinks as $link) {
                $message->attachmentRecords()->create([
                    'conversation_id' => $conversation->id,
                    'uploaded_by' => $actor->id,
                    'type' => ConversationAttachment::TYPE_LINK,
                    'title' => $link['title'] ?? null,
                    'url' => $link['url'],
                ]);
            }

            $conversation->forceFill([
                'last_message_at' => $now,
                'last_message_by' => $actor->id,
                'last_message_preview' => $body !== '' ? str($body)->limit(250)->toString() : '[Attachment]',
                'last_message_metadata' => [
                    'message_id' => $message->public_id,
                    'message_type' => 'text',
                    'has_attachments' => $storedFiles !== [] || $attachmentLinks !== [],
                    'has_links' => $linkUrls !== [],
                ],
            ])->save();

            ConversationParticipant::query()
                ->where('conversation_id', $conversation->id)
                ->where('user_id', $actor->id)
                ->update([
                    'last_read_at' => $now,
                    'last_read_message_id' => $message->id,
                ]);

            return $message;
        });

        return response()->json([
            'data' => new ConversationMessageResource($message->load(['attachmentRecords', 'conversation:id,public_id', 'sender:id,public_id,name,email'])),
        ], 201);
    }

    public function download(Request $request, Conversation $conversation, ConversationMessage $message, ConversationAttachment $conversationAttachment): StreamedResponse|JsonResponse
    {
        $attachment = $this->visibleAttachmentFor($request->user(), $conversation, $message, $conversationAttachment);

        if (! $attachment->hasStoredFile()) {
            return response()->json(['message' => 'This attachment does not have a downloadable file.'], 404);
        }

        if (! Storage::disk($attachment->storageDisk())->exists($attachment->file_path)) {
            return response()->json(['message' => 'The attachment file could not be found.'], 404);
        }

        try {
            return response()->streamDownload(
                fn () => print Storage::disk($attachment->storageDisk())->get($attachment->file_path),
                $attachment->original_filename,
                [
                    'Cache-Control' => 'max-age=0, no-store, private',
                    'Content-Type' => $attachment->mime_type ?: 'application/octet-stream',
                ]
            );
        } catch (Throwable) {
            return response()->json(['message' => 'The attachment file could not be downloaded. Please try again later.'], 503);
        }
    }

    public function preview(Request $request, Conversation $conversation, ConversationMessage $message, ConversationAttachment $conversationAttachment): Response|JsonResponse
    {
        $attachment = $this->visibleAttachmentFor($request->user(), $conversation, $message, $conversationAttachment);

        if (! $attachment->isPreviewable()) {
            return response()->json(['message' => 'This attachment cannot be previewed.'], 404);
        }

        if (! Storage::disk($attachment->storageDisk())->exists($attachment->file_path)) {
            return response()->json(['message' => 'The attachment file could not be found.'], 404);
        }

        try {
            return response(Storage::disk($attachment->storageDisk())->get($attachment->file_path), 200, [
                'Cache-Control' => 'max-age=0, no-store, private',
                'Content-Disposition' => 'inline; filename="'.$attachment->original_filename.'"',
                'Content-Type' => $attachment->mime_type ?: 'application/octet-stream',
            ]);
        } catch (Throwable) {
            return response()->json(['message' => 'The attachment file could not be previewed. Please try again later.'], 503);
        }
    }

    /**
     * Mark the actor participant read through the latest visible message.
     */
    public function markRead(Request $request, Conversation $conversation): JsonResponse
    {
        $actor = $request->user();
        $conversation = $this->visibleConversationFor($actor, $conversation);
        $participant = $this->activeParticipantFor($conversation, $actor);

        $message = $conversation->messages()
            ->latest('created_at')
            ->latest('id')
            ->first();

        $readAt = now();
        $participant->forceFill([
            'last_read_at' => $readAt,
            'last_read_message_id' => $message?->id,
        ])->save();

        return response()->json([
            'data' => [
                'conversation_id' => $conversation->public_id,
                'read_at' => $readAt,
                'last_read_message_id' => $message?->public_id,
                'unread_count' => 0,
            ],
        ]);
    }

    /**
     * Mark the actor participant read through a specific message boundary.
     */
    public function markMessagesRead(Request $request, Conversation $conversation): JsonResponse
    {
        $actor = $request->user();
        $conversation = $this->visibleConversationFor($actor, $conversation);
        $participant = $this->activeParticipantFor($conversation, $actor);

        $validated = $request->validate([
            'message_id' => ['sometimes', 'string', 'exists:conversation_messages,public_id'],
            'message_ids' => ['sometimes', 'array', 'min:1', 'max:100'],
            'message_ids.*' => ['required', 'string', 'distinct', 'exists:conversation_messages,public_id'],
        ]);

        $messageIds = collect($validated['message_ids'] ?? [])
            ->when(isset($validated['message_id']), fn ($ids) => $ids->push($validated['message_id']))
            ->unique()
            ->values();

        if ($messageIds->isEmpty()) {
            throw ValidationException::withMessages([
                'message_id' => 'A message_id or message_ids value is required.',
            ]);
        }

        $messageCount = $conversation->messages()
            ->whereIn('public_id', $messageIds)
            ->count();

        if ($messageCount !== $messageIds->count()) {
            throw ValidationException::withMessages([
                'message_ids' => 'One or more selected messages do not belong to this conversation.',
            ]);
        }

        $message = $conversation->messages()
            ->whereIn('public_id', $messageIds)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->firstOrFail();

        $readAt = now();
        $participant->forceFill([
            'last_read_at' => $readAt,
            'last_read_message_id' => $message->id,
        ])->save();

        return response()->json([
            'data' => [
                'conversation_id' => $conversation->public_id,
                'read_at' => $readAt,
                'last_read_message_id' => $message->public_id,
                'unread_count' => $this->unreadCountFor($participant->refresh(), $actor),
            ],
        ]);
    }

    /**
     * Return the total unread conversation message count for the actor.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $actor = $request->user();

        if (! $actor->can('viewAny', Conversation::class)) {
            abort(403);
        }

        $count = ConversationParticipant::query()
            ->where('user_id', $actor->id)
            ->whereNull('archived_at')
            ->whereNull('deleted_at')
            ->with('lastReadMessage:id,conversation_id,created_at')
            ->get()
            ->sum(fn (ConversationParticipant $participant) => $this->unreadCountFor($participant, $actor));

        return response()->json([
            'data' => [
                'unread_count' => $count,
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

    private function visibleAttachmentFor(User $user, Conversation $conversation, ConversationMessage $message, ConversationAttachment $attachment): ConversationAttachment
    {
        $conversation = $this->visibleConversationFor($user, $conversation);

        if ((int) $message->conversation_id !== (int) $conversation->id
            || (int) $attachment->conversation_id !== (int) $conversation->id
            || (int) $attachment->conversation_message_id !== (int) $message->id) {
            abort(404);
        }

        return $attachment;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<int, array{url: string, title?: string|null}>
     */
    private function normalizedAttachmentLinks(array $validated): array
    {
        $links = collect($validated['attachment_links'] ?? []);

        collect($validated['links'] ?? [])
            ->each(fn (string $url) => $links->push(['url' => $url, 'title' => null]));

        return $links
            ->unique('url')
            ->values()
            ->all();
    }

    private function activeParticipantFor(Conversation $conversation, User $actor): ConversationParticipant
    {
        return $conversation->participants()
            ->where('user_id', $actor->id)
            ->whereNull('archived_at')
            ->whereNull('deleted_at')
            ->firstOrFail();
    }

    private function unreadCountFor(ConversationParticipant $participant, User $actor): int
    {
        $lastReadMessage = $participant->last_read_message_id !== null
            ? ($participant->relationLoaded('lastReadMessage')
                ? $participant->lastReadMessage
                : ConversationMessage::query()
                    ->select(['id', 'conversation_id', 'created_at'])
                    ->find($participant->last_read_message_id))
            : null;

        return ConversationMessage::query()
            ->where('conversation_id', $participant->conversation_id)
            ->where('sender_id', '!=', $actor->id)
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
