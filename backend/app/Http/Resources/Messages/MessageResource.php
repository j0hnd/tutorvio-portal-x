<?php

namespace App\Http\Resources\Messages;

use App\Http\Resources\Concerns\SanitizesApiResponses;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    use SanitizesApiResponses;

    /**
     * Transform a message into a public-safe API response.
     *
     * The response exposes message content, read/delivery state, attachment
     * descriptors, thread/sender/recipient summaries, and timestamps using public
     * IDs. It does not expose private transport metadata or database primary keys.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'thread_id' => $this->whenLoaded('thread', fn () => $this->publicId($this->resource->thread)),
            'sender_id' => $this->whenLoaded('sender', fn () => $this->publicId($this->resource->sender)),
            'sender' => $this->whenLoaded('sender', fn () => $this->userSummary($this->resource->sender, $request)),
            'body' => $this->resource->body,
            'message_type' => $this->resource->message_type,
            'sent_at' => $this->resource->sent_at,
            'edited_at' => $this->resource->edited_at,
            'archived_at' => $this->resource->archived_at,
        ];

        if ($this->canViewAdminFields($request)) {
            $data['metadata'] = $this->resource->metadata ?? [];
            $data['created_at'] = $this->resource->created_at;
            $data['updated_at'] = $this->resource->updated_at;
        }

        return $data;
    }
}
