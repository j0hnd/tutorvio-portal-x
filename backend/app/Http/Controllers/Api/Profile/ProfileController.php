<?php

namespace App\Http\Controllers\Api\Profile;

use App\Enums\AuditActionType;
use App\Enums\AuditModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\Profile\UserResource;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

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
        $originalUser = $user->only(['name', 'phone', 'timezone']);
        $originalStudentProfile = $user->studentProfile?->only(array_keys($data['student_profile'] ?? [])) ?? [];

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

        $this->logStudentProfileUpdate($user, $data, $originalUser, $originalStudentProfile);

        return new UserResource($user);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $originalUser
     * @param  array<string, mixed>  $originalStudentProfile
     */
    private function logStudentProfileUpdate(
        User $user,
        array $data,
        array $originalUser,
        array $originalStudentProfile
    ): void {
        if (! $user->hasRole('student')) {
            return;
        }

        $changedFields = [];
        foreach (['name', 'phone', 'timezone'] as $field) {
            if (array_key_exists($field, $data) && ($originalUser[$field] ?? null) !== $user->{$field}) {
                $changedFields[] = $field;
            }
        }

        foreach (array_keys($data['student_profile'] ?? []) as $field) {
            $originalValue = $originalStudentProfile[$field] ?? null;
            $updatedValue = $user->studentProfile?->{$field};

            if ($originalValue != $updatedValue) {
                $changedFields[] = 'student_profile.'.$field;
            }
        }

        $changedFields = array_values(array_unique($changedFields));
        if ($changedFields === []) {
            return;
        }

        $this->auditLogService->record(
            actorUserId: auth()->id(),
            actionType: AuditActionType::STUDENT_UPDATED,
            module: AuditModule::STUDENTS,
            targetEntityType: 'student_profile',
            targetEntityId: $user->studentProfile?->id ?? $user->id,
            metadata: [
                'student_id' => $user->id,
                'teacher_id' => $user->studentProfile?->assigned_teacher_id,
                'changed_fields' => array_fill_keys($changedFields, true),
            ],
        );
    }
}
