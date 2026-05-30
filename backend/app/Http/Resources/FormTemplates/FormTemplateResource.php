<?php

namespace App\Http\Resources\FormTemplates;

use App\Http\Resources\Concerns\SanitizesApiResponses;
use App\Models\FormTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormTemplateResource extends JsonResource
{
    use SanitizesApiResponses;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->publicId($this->resource),
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
        ];

        if ($this->canViewAdminFields($request, 'form_templates.view')) {
            $data += [
                'created_by' => $this->resource->created_by,
                'updated_by' => $this->resource->updated_by,
                'created_by_user' => $this->whenLoaded('createdBy', fn () => $this->userSummary($this->resource->createdBy, $request)),
                'updated_by_user' => $this->whenLoaded('updatedBy', fn () => $this->userSummary($this->resource->updatedBy, $request)),
                'created_at' => $this->resource->created_at,
                'updated_at' => $this->resource->updated_at,
            ];
        }

        return $data;
    }
}
