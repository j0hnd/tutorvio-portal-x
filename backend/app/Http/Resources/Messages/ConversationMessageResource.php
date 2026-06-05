<?php

namespace App\Http\Resources\Messages;

use App\Http\Resources\Concerns\SanitizesApiResponses;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationMessageResource extends JsonResource
{
    use SanitizesApiResponses;

    /**
     * Transform a conversation message into a public-safe API response.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->publicId($this->resource),
            'conversation_id' => $this->whenLoaded('conversation', fn () => $this->publicId($this->resource->conversation)),
            'sender_id' => $this->whenLoaded('sender', fn () => $this->publicId($this->resource->sender)),
            'sender' => $this->whenLoaded('sender', fn () => $this->userSummary($this->resource->sender, $request)),
            'body' => $this->resource->body,
            'links' => $this->resource->links ?? [],
            'attachments' => $this->resource->attachments ?? [],
            'status' => $this->resource->status,
            'created_at' => $this->resource->created_at,
            'edited_at' => $this->resource->edited_at,
            'deleted_at' => $this->resource->deleted_at,
        ];

        if ($this->canViewAdminFields($request, 'messages.manage')) {
            $data['metadata'] = $this->resource->metadata ?? [];
            $data['updated_at'] = $this->resource->updated_at;
        }

        return $data;
    }
}
