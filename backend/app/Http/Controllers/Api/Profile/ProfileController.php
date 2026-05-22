<?php

namespace App\Http\Controllers\Api\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\Profile\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    public function show(Request $request): UserResource
    {
        $user = $request->user();

        $user->loadMissing([
            'studentProfile',
            'studentProfile.lessons',
            'studentProfile.attendances',
            'studentProfile.materials',
            'studentProfile.learningResources',
            'studentProfile.subscriptions',
            'teacherProfile',
            'teacherProfile.assignedStudents',
            'staffProfile',
        ]);

        return new UserResource($user);
    }

    public function showUser(Request $request, User $user): UserResource|JsonResponse
    {
        $requester = $request->user();

        // 1. Admin/Staff can view anyone (with permissions check, assuming staff has basic view, or needs specific?)
        $canView = false;
        if ($requester->hasRole('admin')) {
            $canView = true;
        } elseif ($requester->hasRole('staff') && $requester->can('users.view')) {
            $canView = true;
        } elseif ($requester->hasRole('teacher') && $user->hasRole('student')) {
            // Teacher can view assigned student
            $canView = $user->studentProfile()->where('assigned_teacher_id', $requester->id)->exists();
        }

        if (! $canView) {
            return response()->json(['message' => 'Unauthorized to view this profile.'], 403);
        }

        $user->loadMissing([
            'studentProfile',
            'studentProfile.lessons',
            'studentProfile.attendances',
            'studentProfile.materials',
            'studentProfile.learningResources',
            'studentProfile.subscriptions',
            'teacherProfile',
            'teacherProfile.assignedStudents',
            'staffProfile',
        ]);

        return new UserResource($user);
    }

    public function update(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();

        return $this->updateProfileData($user, $request->validated());
    }

    public function updateUser(UpdateProfileRequest $request, User $user): UserResource
    {
        return $this->updateProfileData($user, $request->validated());
    }

    private function updateProfileData(User $user, array $data): UserResource
    {
        DB::transaction(function () use ($user, $data) {
            $user->update(array_intersect_key($data, array_flip(['name', 'phone', 'timezone'])));

            if (isset($data['student_profile']) && $user->studentProfile) {
                $user->studentProfile->update($data['student_profile']);
            }

            if (isset($data['teacher_profile']) && $user->teacherProfile) {
                $user->teacherProfile->update($data['teacher_profile']);
            }

            if (isset($data['staff_profile']) && $user->staffProfile) {
                $user->staffProfile->update($data['staff_profile']);
            }
        });

        $user->refresh()->loadMissing([
            'studentProfile',
            'teacherProfile',
            'staffProfile',
        ]);

        return new UserResource($user);
    }
}
