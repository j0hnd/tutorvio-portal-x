<?php

namespace App\Http\Resources\Messages;

use App\Http\Resources\Concerns\SanitizesApiResponses;
use App\Models\ConversationMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    use SanitizesApiResponses;

    /**
     * Transform a conversation into a public-safe API response.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $unreadMessageCount = $this->unreadMessageCount($request);

        $data = [
            'id' => $this->publicId($this->resource),
            'type' => $this->resource->type,
            'title' => $this->resource->title,
            'display_title' => $this->displayTitle($request),
            'status' => $this->resource->status,
            'is_archived' => $this->resource->status === 'archived',
            'is_closed' => $this->resource->status === 'closed',
            'student_id' => $this->whenLoaded('student', fn () => $this->publicId($this->resource->student)),
            'teacher_id' => $this->whenLoaded('teacher', fn () => $this->publicId($this->resource->teacher)),
            'course_program_id' => $this->whenLoaded('courseProgram', fn () => $this->publicId($this->resource->courseProgram)),
            'last_message_at' => $this->resource->last_message_at,
            'last_message_by' => $this->whenLoaded('lastMessageBy', fn () => $this->publicId($this->resource->lastMessageBy)),
            'last_message_preview' => $this->resource->last_message_preview,
            'last_message_metadata' => $this->resource->last_message_metadata ?? [],
            'metadata' => $this->resource->metadata ?? [],
            'unread_count' => $unreadMessageCount,
            'unread_message_count' => $unreadMessageCount,
            'is_pinned' => $this->isPinned($request),
            'participant_summary' => $this->participantSummary($request),
            'participants' => $this->whenLoaded('participants', fn () => $this->resource->participants->map(fn ($participant) => [
                'user_id' => $participant->relationLoaded('user') ? $this->publicId($participant->user) : null,
                'participant_role' => $participant->participant_role,
                'participant_role_snapshot' => $participant->participant_role_snapshot,
                'participant_roles_snapshot' => $participant->participant_roles_snapshot ?? [],
                'joined_at' => $participant->joined_at,
                'last_read_at' => $participant->last_read_at,
                'last_read_message_id' => $participant->relationLoaded('lastReadMessage') ? $this->publicId($participant->lastReadMessage) : null,
                'muted_at' => $participant->muted_at,
                'archived_at' => $participant->archived_at,
                'user' => $participant->relationLoaded('user') ? [
                    'id' => $this->publicId($participant->user),
                    'name' => $participant->user?->name,
                    'email' => $participant->user?->email,
                ] : null,
            ])->values()),
            'permission_metadata' => $this->permissionMetadata($request),
        ];

        if ($this->canViewConversationAdminFields($request)) {
            $data['created_by'] = $this->whenLoaded('createdBy', fn () => $this->publicId($this->resource->createdBy));
            $data['created_at'] = $this->resource->created_at;
            $data['updated_at'] = $this->resource->updated_at;
        }

        return $data;
    }

    private function canViewConversationAdminFields(Request $request): bool
    {
        $user = $request->user();

        return $user?->hasRole('admin') === true
            || ($user?->hasRole('staff') === true && ($user->can('messages.view') || $user->can('messages.manage')));
    }

    private function displayTitle(Request $request): ?string
    {
        if ($this->resource->title !== null && $this->resource->title !== '') {
            return $this->resource->title;
        }

        if (! $this->resource->relationLoaded('participants')) {
            return null;
        }

        $userId = $request->user()?->id;

        return $this->resource->participants
            ->filter(fn ($participant) => (int) $participant->user_id !== (int) $userId)
            ->map(fn ($participant) => $participant->relationLoaded('user') ? $participant->user?->name : null)
            ->filter()
            ->values()
            ->implode(', ') ?: null;
    }

    /**
     * @return array<string, mixed>
     */
    private function participantSummary(Request $request): array
    {
        if (! $this->resource->relationLoaded('participants')) {
            return [
                'total' => 0,
                'preview' => [],
            ];
        }

        $userId = $request->user()?->id;
        $activeParticipants = $this->resource->participants
            ->filter(fn ($participant) => $participant->archived_at === null);

        return [
            'total' => $activeParticipants->count(),
            'preview' => $activeParticipants
                ->sortBy(fn ($participant) => (int) $participant->user_id === (int) $userId)
                ->take(5)
                ->map(fn ($participant) => [
                    'user_id' => $participant->relationLoaded('user') ? $this->publicId($participant->user) : null,
                    'name' => $participant->relationLoaded('user') ? $participant->user?->name : null,
                    'participant_role' => $participant->participant_role,
                ])
                ->values(),
        ];
    }

    private function unreadMessageCount(Request $request): int
    {
        $user = $request->user();
        $participant = $this->currentParticipant($request);

        if (! $user instanceof User || $participant === null || $this->resource->last_message_at === null) {
            return 0;
        }

        return ConversationMessage::query()
            ->where('conversation_id', $this->resource->id)
            ->where('sender_id', '!=', $user->id)
            ->when(
                $participant->last_read_message_id !== null,
                fn (Builder $query) => $this->afterLastReadMessage($query, $participant),
                fn (Builder $query) => $query->when(
                    $participant->last_read_at !== null,
                    fn (Builder $query) => $query->where('created_at', '>', $participant->last_read_at)
                )
            )
            ->count();
    }

    private function afterLastReadMessage(Builder $query, mixed $participant): Builder
    {
        $lastReadMessage = $participant->relationLoaded('lastReadMessage')
            ? $participant->lastReadMessage
            : ConversationMessage::query()
                ->select(['id', 'created_at'])
                ->find($participant->last_read_message_id);

        if ($lastReadMessage === null) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($lastReadMessage, $participant): void {
            $query
                ->where('created_at', '>', $lastReadMessage->created_at)
                ->orWhere(function (Builder $query) use ($lastReadMessage, $participant): void {
                    $query
                        ->where('created_at', $lastReadMessage->created_at)
                        ->where('id', '>', $participant->last_read_message_id);
                });
        });
    }

    private function isPinned(Request $request): bool
    {
        $participant = $this->currentParticipant($request);
        $metadata = $participant?->metadata ?? [];

        return (bool) ($metadata['is_pinned'] ?? $metadata['pinned'] ?? false)
            || ($metadata['pinned_at'] ?? null) !== null;
    }

    /**
     * @return array<string, mixed>
     */
    private function permissionMetadata(Request $request): array
    {
        $user = $request->user();
        $participant = $this->currentParticipant($request);
        $canManage = $user !== null && $user->can('manage', $this->resource);
        $canWrite = $this->resource->status === 'active'
            && ($canManage || ($participant !== null && $participant->archived_at === null));

        return [
            'current_user_id' => $user !== null ? $this->publicId($user) : null,
            'is_participant' => $participant !== null,
            'participant_role' => $participant?->participant_role,
            'participant_role_snapshot' => $participant?->participant_role_snapshot,
            'participant_roles_snapshot' => $participant?->participant_roles_snapshot ?? [],
            'last_read_at' => $participant?->last_read_at,
            'last_read_message_id' => $participant !== null && $participant->relationLoaded('lastReadMessage') ? $this->publicId($participant->lastReadMessage) : null,
            'muted_at' => $participant?->muted_at,
            'archived_at' => $participant?->archived_at,
            'can_send_messages' => $canWrite,
            'can_upload_files' => $canWrite,
            'can_pin_messages' => $canManage,
            'can_close_conversation' => $canManage,
            'can_archive_conversation' => $canManage,
        ];
    }

    private function currentParticipant(Request $request): mixed
    {
        $user = $request->user();

        if ($user === null || ! $this->resource->relationLoaded('participants')) {
            return null;
        }

        return $this->resource->participants->first(fn ($participant) => (int) $participant->user_id === (int) $user->id);
    }
}
