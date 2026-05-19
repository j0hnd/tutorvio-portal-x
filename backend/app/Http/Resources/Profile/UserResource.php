<?php

namespace App\Http\Resources\Profile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isAdminOrStaff = $user->hasRole(['admin', 'staff']);

        $data = [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'phone' => $this->resource->phone,
            'timezone' => $this->resource->timezone,
            'profile_photo_path' => $this->resource->profile_photo_path,
            'roles' => $this->resource->roles->pluck('name'),
        ];

        if ($isAdminOrStaff || $user->id === $this->resource->id) {
            $data['status'] = $this->resource->status;
            $data['created_at'] = $this->resource->created_at;
        }

        if ($isAdminOrStaff) {
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
