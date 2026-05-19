<?php

namespace App\Http\Resources\Profile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'department' => $this->resource->department,
            'access_limitations' => $this->resource->access_limitations,
        ];
    }
}
