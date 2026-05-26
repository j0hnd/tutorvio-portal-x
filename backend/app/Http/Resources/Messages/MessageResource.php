<?php

namespace App\Http\Resources\Messages;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'thread_id' => $this->resource->message_thread_id,
            'sender_id' => $this->resource->sender_id,
            'sender' => $this->whenLoaded('sender', fn () => [
                'id' => $this->resource->sender?->id,
                'name' => $this->resource->sender?->name,
                'email' => $this->resource->sender?->email,
            ]),
            'body' => $this->resource->body,
            'message_type' => $this->resource->message_type,
            'sent_at' => $this->resource->sent_at,
            'edited_at' => $this->resource->edited_at,
            'archived_at' => $this->resource->archived_at,
            'metadata' => $this->resource->metadata ?? [],
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
