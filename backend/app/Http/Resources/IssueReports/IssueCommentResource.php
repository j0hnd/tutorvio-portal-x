<?php

namespace App\Http\Resources\IssueReports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IssueCommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'issue_report_id' => $this->resource->issue_report_id,
            'author_id' => $this->resource->author_id,
            'comment_type' => $this->resource->comment_type,
            'body' => $this->resource->body,
            'is_internal' => $this->resource->is_internal,
            'author' => $this->whenLoaded('author', fn () => $this->resource->author ? [
                'id' => $this->resource->author->id,
                'name' => $this->resource->author->name,
                'email' => $this->resource->author->email,
            ] : null),
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
