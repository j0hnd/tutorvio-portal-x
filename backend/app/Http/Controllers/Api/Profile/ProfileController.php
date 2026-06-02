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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProfileController extends Controller
{
    /**
     * Create the controller with its service dependencies.
     *
     * The framework resolves this constructor before action-specific route
     * middleware, permissions, validation, and authorization are applied.
     *
     * @param  AuditLogService  $auditLogService
     */
    public function __construct(private readonly AuditLogService $auditLogService) {}

    /**
     * Display the selected profile record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a user resource payload.
     *
     * @param  Request  $request
     * @return UserResource
     */
    public function show(Request $request): UserResource
    {
        $user = $request->user();

        $user->loadMissing($this->profileRelationsFor($user));

        return new UserResource($user);
    }

    /**
     * Handle the show user action for profile records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $user.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a user resource payload.
     *
     * @param  Request  $request
     * @param  User  $user
     * @return UserResource|JsonResponse
     */
    public function showUser(Request $request, User $user): UserResource|JsonResponse
    {
        $requester = $request->user();

        try {
            $this->authorizeUserProfileAccess($requester, $user, 'view');
        } catch (NotFoundHttpException) {
            return response()->json(['message' => 'Profile not found.'], 404);
        }

        $user->loadMissing($this->profileRelationsFor($requester));

        return new UserResource($user);
    }

    /**
     * Update the selected profile record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * The UpdateProfileRequest handles authorization and validation before the controller action runs.
     * Returns a user resource payload.
     *
     * @param  UpdateProfileRequest  $request
     * @return UserResource
     */
    public function update(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();

        return $this->updateProfileData($user, $request->validated());
    }

    /**
     * Handle the update user action for profile records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $user.
     * The UpdateProfileRequest handles authorization and validation before the controller action runs.
     * Returns a user resource payload.
     *
     * @param  UpdateProfileRequest  $request
     * @param  User  $user
     * @return UserResource
     */
    public function updateUser(UpdateProfileRequest $request, User $user): UserResource
    {
        $this->authorizeUserProfileAccess($request->user(), $user, 'update');

        return $this->updateProfileData($user, $request->validated());
    }

    /**
     * @return array<int, string>
     *
     * @param  User  $actor
     */
    private function profileRelationsFor(User $actor): array
    {
        $relations = [
            'studentProfile',
            'studentProfile.lessons',
            'studentProfile.attendances',
            'studentProfile.materials',
            'studentProfile.learningResources',
            'teacherProfile',
            'teacherProfile.assignedStudents',
            'staffProfile',
        ];

        if ($actor->hasRole('admin') || ($actor->hasRole('staff') && $actor->can('subscriptions.view'))) {
            $relations[] = 'studentProfile.subscriptions';
        }

        return $relations;
    }

    /**
     * Handle the authorize user profile access action for profile records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Route model parameters include $actor, $target, $operation.
     * The method can return a forbidden response when authorization or ownership checks fail.
     * Returns a JSON response containing the requested data.
     *
     * @param  User  $actor
     * @param  User  $target
     * @param  string  $operation
     * @return void
     */
    private function authorizeUserProfileAccess(User $actor, User $target, string $operation): void
    {
        if ($actor->hasRole('admin')) {
            return;
        }

        if ($actor->hasRole('staff')) {
            $permission = $operation === 'update' ? 'users.update' : 'users.view';
            abort_unless($actor->can($permission), 403);

            return;
        }

        if ($operation === 'update' && (int) $actor->id === (int) $target->id) {
            return;
        }

        if (! $actor->hasRole('teacher') || ! $target->hasRole('student')) {
            abort(403);
        }

        $assigned = $target->studentProfile()
            ->where('assigned_teacher_id', $actor->id)
            ->exists();

        if (! $assigned) {
            throw new NotFoundHttpException;
        }
    }

    /**
     * Handle the update profile data action for profile records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Route model parameters include $user, $data.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a user resource payload.
     *
     * @param  User  $user
     * @param  array  $data
     * @return UserResource
     */
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
     *
     * @param  User  $user
     * @param  array  $data
     * @param  array  $originalUser
     * @param  array  $originalStudentProfile
     * @return void
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
