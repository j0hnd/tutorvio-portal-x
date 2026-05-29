<?php

namespace App\Http\Resources\Messages;

use App\Http\Resources\Concerns\SanitizesApiResponses;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageThreadResource extends JsonResource
{
    use SanitizesApiResponses;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        $data = [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'thread_type' => $this->resource->thread_type,
            'status' => $this->resource->status,
            'student_id' => $this->resource->student_id,
            'teacher_id' => $this->resource->teacher_id,
            'last_message_at' => $this->resource->last_message_at,
            'is_archived' => $this->resource->is_archived,
            'archived_at' => $this->resource->archived_at,
            'unread_count' => $user instanceof User ? $this->unreadCountFor($user) : 0,
            'participants' => $this->whenLoaded('participants', fn () => $this->resource->participants->map(fn ($participant) => [
                'user_id' => $participant->user_id,
                'participant_role' => $participant->participant_role,
                'last_read_at' => $participant->last_read_at,
                'archived_at' => $participant->archived_at,
                'user' => $participant->relationLoaded('user') ? [
                    'id' => $participant->user?->id,
                    'name' => $participant->user?->name,
                    'email' => $participant->user?->email,
                ] : null,
            ])->values()),
            'latest_message' => $this->whenLoaded('latestMessage', fn () => $this->resource->latestMessage
                ? new MessageResource($this->resource->latestMessage)
                : null),
        ];

        if ($this->canViewAdminFields($request)) {
            $data['created_by'] = $this->resource->created_by;
            $data['metadata'] = $this->resource->metadata ?? [];
            $data['created_at'] = $this->resource->created_at;
            $data['updated_at'] = $this->resource->updated_at;
        }

        return $data;
    }

    private function unreadCountFor(User $user): int
    {
        $participant = $this->resource->participants
            ->firstWhere('user_id', $user->id);

        if ($participant === null) {
            return 0;
        }

        return $this->resource->messages()
            ->where('sender_id', '!=', $user->id)
            ->whereNull('archived_at')
            ->when(
                $participant->last_read_at !== null,
                fn ($query) => $query->where('sent_at', '>', $participant->last_read_at)
            )
            ->count();
    }
}
