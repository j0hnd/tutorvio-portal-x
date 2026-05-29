<?php

namespace App\Http\Resources\Messages;

use App\Http\Resources\Concerns\SanitizesApiResponses;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    use SanitizesApiResponses;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->resource->id,
            'thread_id' => $this->resource->message_thread_id,
            'sender_id' => $this->resource->sender_id,
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
