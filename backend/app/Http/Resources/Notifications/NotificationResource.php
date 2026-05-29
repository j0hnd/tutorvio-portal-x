<?php

namespace App\Http\Resources\Notifications;

use App\Http\Resources\Concerns\SanitizesApiResponses;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    use SanitizesApiResponses;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $notification = $this->resource->notification;

        $data = [
            'id' => $notification->id,
            'recipient_id' => $this->resource->id,
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

        if ($this->canViewAdminFields($request, 'notifications.history.view')) {
            $data['recipient_user_id'] = $this->resource->user_id;
            $data['channel'] = $this->resource->channel;
            $data['delivery_status'] = $this->resource->delivery_status;
            $data['metadata'] = $notification->metadata ?? [];
        }

        return $data;
    }
}
