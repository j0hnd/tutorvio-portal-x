<?php

namespace App\Http\Resources\Messages;

use App\Http\Resources\Concerns\SanitizesApiResponses;
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
        $data = [
            'id' => $this->publicId($this->resource),
            'type' => $this->resource->type,
            'title' => $this->resource->title,
            'status' => $this->resource->status,
            'student_id' => $this->whenLoaded('student', fn () => $this->publicId($this->resource->student)),
            'teacher_id' => $this->whenLoaded('teacher', fn () => $this->publicId($this->resource->teacher)),
            'course_program_id' => $this->whenLoaded('courseProgram', fn () => $this->publicId($this->resource->courseProgram)),
            'last_message_at' => $this->resource->last_message_at,
            'last_message_preview' => $this->resource->last_message_preview,
            'participants' => $this->whenLoaded('participants', fn () => $this->resource->participants->map(fn ($participant) => [
                'user_id' => $participant->relationLoaded('user') ? $this->publicId($participant->user) : null,
                'participant_role' => $participant->participant_role,
                'joined_at' => $participant->joined_at,
                'last_read_at' => $participant->last_read_at,
                'archived_at' => $participant->archived_at,
                'user' => $participant->relationLoaded('user') ? [
                    'id' => $this->publicId($participant->user),
                    'name' => $participant->user?->name,
                    'email' => $participant->user?->email,
                ] : null,
            ])->values()),
        ];

        if ($this->canViewConversationAdminFields($request)) {
            $data['created_by'] = $this->whenLoaded('createdBy', fn () => $this->publicId($this->resource->createdBy));
            $data['metadata'] = $this->resource->metadata ?? [];
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
}
