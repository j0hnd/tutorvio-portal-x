<?php

namespace App\Http\Resources\Messages;

use App\Http\Resources\Concerns\SanitizesApiResponses;
use App\Models\MessageTemplate;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageTemplateResource extends JsonResource
{
    use SanitizesApiResponses;

    /**
     * Transform a message template into a public-safe API response.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->publicId($this->resource),
            'title' => $this->resource->title,
            'body' => $this->resource->body,
            'category' => $this->resource->category,
            'role_visibility' => $this->resource->role_visibility,
            'status' => $this->resource->status,
            'is_active' => $this->resource->status === MessageTemplate::STATUS_ACTIVE,
            'teacher_id' => $this->publicIdFor(User::class, $this->resource->teacher_id),
            'teacher' => $this->whenLoaded('teacher', fn () => $this->userSummary($this->resource->teacher, $request)),
        ];

        if ($this->canViewAdminFields($request, 'message_templates.view')) {
            $data += [
                'created_by' => $this->publicIdFor(User::class, $this->resource->created_by),
                'updated_by' => $this->publicIdFor(User::class, $this->resource->updated_by),
                'created_by_user' => $this->whenLoaded('createdBy', fn () => $this->userSummary($this->resource->createdBy, $request)),
                'updated_by_user' => $this->whenLoaded('updatedBy', fn () => $this->userSummary($this->resource->updatedBy, $request)),
                'created_at' => $this->resource->created_at,
                'updated_at' => $this->resource->updated_at,
            ];
        }

        return $data;
    }
}
