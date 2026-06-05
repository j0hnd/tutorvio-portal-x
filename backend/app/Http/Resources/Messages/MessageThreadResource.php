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
     * Transform a message thread into a public-safe API response.
     *
     * Public fields expose the thread public ID, subject/status, participant
     * summaries, last-message state, unread counts, and loaded messages. User and
     * message references use public IDs, and private database keys remain internal.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        $data = [
            'id' => $this->publicId($this->resource),
            'title' => $this->resource->title,
            'thread_type' => $this->resource->thread_type,
            'status' => $this->resource->status,
            'student_id' => $this->whenLoaded('student', fn () => $this->publicId($this->resource->student)),
            'teacher_id' => $this->whenLoaded('teacher', fn () => $this->publicId($this->resource->teacher)),
            'last_message_at' => $this->resource->last_message_at,
            'is_archived' => $this->resource->is_archived,
            'archived_at' => $this->resource->archived_at,
            'unread_count' => $user instanceof User ? $this->unreadCountFor($user) : 0,
            'participants' => $this->whenLoaded('participants', fn () => $this->resource->participants->map(fn ($participant) => [
                'user_id' => $participant->relationLoaded('user') ? $this->publicId($participant->user) : null,
                'participant_role' => $participant->participant_role,
                'last_read_at' => $participant->last_read_at,
                'archived_at' => $participant->archived_at,
                'user' => $participant->relationLoaded('user') ? [
                    'id' => $this->publicId($participant->user),
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
