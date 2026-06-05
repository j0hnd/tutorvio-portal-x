<?php

namespace App\Http\Resources\Notifications;

use App\Http\Resources\Concerns\SanitizesApiResponses;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    use SanitizesApiResponses;

    /**
     * Transform a notification into a public-safe API response.
     *
     * The response exposes notification content, type, read state, action data,
     * and timestamps for the intended recipient. Database primary keys and private
     * delivery internals should not be exposed in frontend-facing references.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $notification = $this->resource->notification;

        $data = [
            'id' => $this->publicId($notification),
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
            $data['recipient_user_id'] = $this->publicIdFor(User::class, $this->resource->user_id);
            $data['channel'] = $this->resource->channel;
            $data['delivery_status'] = $this->resource->delivery_status;
            $data['metadata'] = $notification->metadata ?? [];
        }

        return $data;
    }
}
