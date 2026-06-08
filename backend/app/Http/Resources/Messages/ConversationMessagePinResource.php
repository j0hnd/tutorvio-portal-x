<?php

namespace App\Http\Resources\Messages;

use App\Http\Resources\Concerns\SanitizesApiResponses;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationMessagePinResource extends JsonResource
{
    use SanitizesApiResponses;

    /**
     * Transform a pinned message into a public-safe API response.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->publicId($this->resource),
            'conversation_id' => $this->whenLoaded('conversation', fn () => $this->publicId($this->resource->conversation)),
            'message_id' => $this->whenLoaded('message', fn () => $this->publicId($this->resource->message)),
            'pinned_by' => $this->whenLoaded('pinnedBy', fn () => $this->publicId($this->resource->pinnedBy)),
            'pinned_by_user' => $this->whenLoaded('pinnedBy', fn () => $this->userSummary($this->resource->pinnedBy, $request)),
            'pinned_at' => $this->resource->pinned_at,
            'message' => $this->whenLoaded('message', fn () => new ConversationMessageResource($this->resource->message)),
        ];
    }
}
