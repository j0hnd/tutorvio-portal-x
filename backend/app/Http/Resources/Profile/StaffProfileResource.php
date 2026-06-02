<?php

namespace App\Http\Resources\Profile;

use App\Http\Resources\Concerns\SanitizesApiResponses;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffProfileResource extends JsonResource
{
    use SanitizesApiResponses;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->staffPublicId(),
            'department' => $this->resource->department,
            'access_limitations' => $this->resource->access_limitations,
        ];
    }

    private function staffPublicId(): ?string
    {
        if ($this->relationLoaded('user')) {
            return $this->publicId($this->resource->user);
        }

        return User::query()
            ->whereKey($this->resource->user_id)
            ->value('public_id');
    }
}
