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
            'attachments' => $this->when(
                $this->resource->relationLoaded('attachmentRecords'),
                fn () => $this->resource->attachmentRecords->map(fn ($attachment) => [
                    'id' => $this->publicId($attachment),
                    'type' => $attachment->type,
                    'title' => $attachment->title,
                    'url' => $attachment->type === 'link' ? $attachment->url : null,
                    'original_filename' => $attachment->original_filename,
                    'mime_type' => $attachment->mime_type,
                    'file_size' => $attachment->file_size,
                    'download' => $attachment->hasStoredFile() ? [
                        'endpoint' => url('/api/v1/conversations/'.$this->publicId($this->resource->conversation).'/messages/'.$this->publicId($this->resource).'/attachments/'.$this->publicId($attachment).'/download'),
                    ] : null,
                    'preview' => $attachment->isPreviewable() ? [
                        'endpoint' => url('/api/v1/conversations/'.$this->publicId($this->resource->conversation).'/messages/'.$this->publicId($this->resource).'/attachments/'.$this->publicId($attachment).'/preview'),
                    ] : null,
                    'created_at' => $attachment->created_at,
                ])->values(),
                $this->resource->attachments ?? []
            ),
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
