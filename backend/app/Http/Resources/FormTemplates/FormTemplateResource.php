<?php

namespace App\Http\Resources\FormTemplates;

use App\Models\FormTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'key' => $this->resource->key,
            'title' => $this->resource->name,
            'name' => $this->resource->name,
            'description' => $this->resource->description,
            'category' => $this->resource->template_type,
            'type' => $this->resource->template_type,
            'template_type' => $this->resource->template_type,
            'status' => $this->resource->status,
            'is_active' => $this->resource->status === FormTemplate::STATUS_ACTIVE,
            'is_archived' => $this->resource->status === FormTemplate::STATUS_ARCHIVED,
            'version' => $this->resource->version,
            'schema' => $this->resource->schema,
            'fields' => $this->resource->schema['fields'] ?? [],
            'instructions' => $this->resource->instructions,
            'created_by' => $this->resource->created_by,
            'updated_by' => $this->resource->updated_by,
            'created_by_user' => $this->whenLoaded('createdBy', fn () => $this->resource->createdBy ? [
                'id' => $this->resource->createdBy->id,
                'name' => $this->resource->createdBy->name,
                'email' => $this->resource->createdBy->email,
            ] : null),
            'updated_by_user' => $this->whenLoaded('updatedBy', fn () => $this->resource->updatedBy ? [
                'id' => $this->resource->updatedBy->id,
                'name' => $this->resource->updatedBy->name,
                'email' => $this->resource->updatedBy->email,
            ] : null),
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
