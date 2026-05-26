<?php

namespace App\Http\Resources\Notifications;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $notification = $this->resource->notification;

        return [
            'id' => $notification->id,
            'recipient_id' => $this->resource->id,
            'recipient_user_id' => $this->resource->user_id,
            'channel' => $this->resource->channel,
            'delivery_status' => $this->resource->delivery_status,
            'title' => $notification->title,
            'body' => $notification->body,
            'message' => $notification->body,
            'type' => $notification->type,
            'is_read' => $this->resource->read_at !== null,
            'read_status' => $this->resource->read_at === null ? 'unread' : 'read',
            'read_at' => $this->resource->read_at,
            'created_at' => $notification->created_at,
            'published_at' => $notification->published_at,
            'metadata' => $notification->metadata ?? [],
        ];
    }
}
