<?php

namespace App\Http\Resources\Profile;

use App\Http\Resources\Concerns\SanitizesApiResponses;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    use SanitizesApiResponses;

    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isAdminOrStaff = $this->canViewAdminFields($request);
        $isOwnProfile = (int) $user->id === (int) $this->resource->id;

        $data = [
            'id' => $this->publicId($this->resource),
            'name' => $this->resource->name,
            'phone' => $this->resource->phone,
            'timezone' => $this->resource->timezone,
        ];

        if ($isAdminOrStaff || $isOwnProfile) {
            $data['email'] = $this->resource->email;
        }

        if ($isAdminOrStaff) {
            $data['roles'] = $this->resource->roles->pluck('name');
            $data['status'] = $this->resource->status;
            $data['created_at'] = $this->resource->created_at;
            $data['profile_photo_path'] = $this->resource->profile_photo_path;
            $data['signed_document_path'] = $this->resource->signed_document_path;
            $data['email_verified_at'] = $this->resource->email_verified_at;
            $data['invited_at'] = $this->resource->invited_at;
            $data['activated_at'] = $this->resource->activated_at;
        }

        // Add specific profiles based on what is loaded
        if ($this->relationLoaded('studentProfile') && $this->resource->studentProfile) {
            $data['student_profile'] = new StudentProfileResource($this->resource->studentProfile);
        }

        if ($this->relationLoaded('teacherProfile') && $this->resource->teacherProfile) {
            $data['teacher_profile'] = new TeacherProfileResource($this->resource->teacherProfile);
        }

        if ($this->relationLoaded('staffProfile') && $this->resource->staffProfile) {
            $data['staff_profile'] = new StaffProfileResource($this->resource->staffProfile);
        }

        return $data;
    }
}
