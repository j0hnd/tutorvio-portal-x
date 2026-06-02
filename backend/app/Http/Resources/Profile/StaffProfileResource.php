<?php

namespace App\Http\Resources\Profile;

use App\Http\Resources\Concerns\SanitizesApiResponses;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffProfileResource extends JsonResource
{
    use SanitizesApiResponses;

    /**
     * Transform a staff profile into a public-safe profile response.
     *
     * The response exposes profile details and the linked user reference using
     * public IDs. It should not expose database primary keys or private staff
     * internals beyond the fields already selected for the API profile contract.
     *
     * @return array<string, mixed>
     */
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
