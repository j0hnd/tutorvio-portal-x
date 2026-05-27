<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\AuditActionType;
use App\Enums\AuditModule;
use App\Http\Controllers\Controller;
use App\Models\StudentProfile;
use App\Models\User;
use App\Models\UserStatusHistory;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

class UserManagementController extends Controller
{
    private const MANAGED_ROLES = ['student', 'teacher', 'admin', 'staff'];

    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['sometimes', 'string', Rule::in(self::MANAGED_ROLES)],
            'status' => ['sometimes', 'string', Rule::in(User::STATUSES)],
            'search' => ['sometimes', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $users = User::query()
            ->with(['roles', 'permissions', 'studentProfile.assignedTeacher', 'teacherProfile', 'staffProfile'])
            ->when($validated['role'] ?? null, fn ($query, string $role) => $query->role($role))
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($validated['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($validated['per_page'] ?? 25);

        return response()->json($users->through(fn (User $user) => $this->serializeUser($user)));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateUserPayload($request, true);

        $user = DB::transaction(function () use ($validated, $request) {
            $role = $validated['role'];

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'timezone' => $validated['timezone'] ?? null,
                'profile_photo_path' => $validated['profile_photo_path'] ?? null,
                'signed_document_path' => $validated['signed_document_path'] ?? null,
                'password' => $validated['password'] ?? Str::password(32),
                'status' => $validated['status'] ?? User::STATUS_INVITED,
                'invited_at' => ($validated['status'] ?? User::STATUS_INVITED) === User::STATUS_INVITED ? now() : null,
                'activated_at' => ($validated['status'] ?? null) === User::STATUS_ACTIVE ? now() : null,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            $user->assignRole($role);
            $this->syncRoleProfile($user, $role, $validated);
            $this->syncStaffPermissions($user, $role, $validated['permissions'] ?? null);

            UserStatusHistory::create([
                'user_id' => $user->id,
                'old_status' => null,
                'new_status' => $user->status,
                'reason' => $validated['status_reason'] ?? 'User created',
                'changed_by' => $request->user()->id,
                'changed_at' => now(),
            ]);

            return $user;
        });

        return response()->json([
            'data' => $this->serializeUser($user->fresh($this->userRelations())),
        ], 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json([
            'data' => $this->serializeUser($user->load($this->userRelations())),
        ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $this->validateUserPayload($request, false, $user);
        $actorId = $request->user()->id;
        $oldPrimaryRole = $this->primaryRole($user);
        $oldPermissions = $this->directPermissionNames($user);
        $oldStaffAccessLevel = $user->staffProfile?->access_limitations;

        $user = DB::transaction(function () use ($validated, $actorId, $user) {
            $role = $validated['role'] ?? $this->primaryRole($user);
            $oldStatus = $user->status;

            $user->fill(Arr::only($validated, [
                'name',
                'email',
                'phone',
                'timezone',
                'profile_photo_path',
                'signed_document_path',
                'status',
            ]));

            if (array_key_exists('password', $validated)) {
                $user->password = $validated['password'];
            }

            $user->updated_by = $actorId;
            $user->save();

            if (array_key_exists('status', $validated) && $oldStatus !== $user->status) {
                UserStatusHistory::create([
                    'user_id' => $user->id,
                    'old_status' => $oldStatus,
                    'new_status' => $user->status,
                    'reason' => $validated['status_reason'] ?? null,
                    'changed_by' => $actorId,
                    'changed_at' => now(),
                ]);
            }

            if (array_key_exists('role', $validated)) {
                $user->syncRoles([$role]);
            }

            if ($role !== null) {
                $this->syncRoleProfile($user, $role, $validated);
            }

            if (array_key_exists('permissions', $validated)) {
                $this->syncStaffPermissions($user, $role, $validated['permissions']);
            }

            return $user;
        });
        $user = $user->fresh($this->userRelations());
        $this->auditAccessControlChanges(
            actorUserId: $actorId,
            targetUser: $user,
            oldRole: $oldPrimaryRole,
            newRole: $this->primaryRole($user),
            oldPermissions: $oldPermissions,
            newPermissions: $this->directPermissionNames($user),
            oldStaffAccessLevel: $oldStaffAccessLevel,
            newStaffAccessLevel: $user->staffProfile?->access_limitations,
        );

        return response()->json([
            'data' => $this->serializeUser($user),
        ]);
    }

    public function activate(Request $request, User $user): JsonResponse
    {
        return $this->changeStatus($request, $user, User::STATUS_ACTIVE);
    }

    public function deactivate(Request $request, User $user): JsonResponse
    {
        return $this->changeStatus($request, $user, User::STATUS_INACTIVE);
    }

    public function syncRoles(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', Rule::in(self::MANAGED_ROLES)],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ]);

        $this->validateStaffPermissions($validated['role'], $validated['permissions'] ?? null);

        $actorId = $request->user()->id;
        $oldRole = $this->primaryRole($user);
        $oldPermissions = $this->directPermissionNames($user);
        $oldStaffAccessLevel = $user->staffProfile?->access_limitations;

        DB::transaction(function () use ($validated, $actorId, $user) {
            $user->syncRoles([$validated['role']]);
            $user->updated_by = $actorId;
            $user->save();

            $this->syncRoleProfile($user, $validated['role'], []);
            $this->syncStaffPermissions($user, $validated['role'], $validated['permissions'] ?? null);
        });
        $user = $user->fresh($this->userRelations());
        $this->auditAccessControlChanges(
            actorUserId: $actorId,
            targetUser: $user,
            oldRole: $oldRole,
            newRole: $this->primaryRole($user),
            oldPermissions: $oldPermissions,
            newPermissions: $this->directPermissionNames($user),
            oldStaffAccessLevel: $oldStaffAccessLevel,
            newStaffAccessLevel: $user->staffProfile?->access_limitations,
        );

        return response()->json([
            'data' => $this->serializeUser($user),
        ]);
    }

    public function statusHistory(User $user): JsonResponse
    {
        return response()->json([
            'data' => $user->statusHistories()
                ->with('changedBy:id,name,email')
                ->latest('changed_at')
                ->get()
                ->map(fn (UserStatusHistory $history) => [
                    'id' => $history->id,
                    'user_id' => $history->user_id,
                    'old_status' => $history->old_status,
                    'new_status' => $history->new_status,
                    'reason' => $history->reason,
                    'changed_by' => $history->changed_by,
                    'changed_by_user' => $history->changedBy,
                    'changed_at' => $history->changed_at,
                    'created_at' => $history->created_at,
                    'updated_at' => $history->updated_at,
                ]),
        ]);
    }

    private function changeStatus(Request $request, User $user, string $status): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $user, $status, $validated) {
            $oldStatus = $user->status;

            $user->status = $status;
            $user->activated_at = $status === User::STATUS_ACTIVE ? now() : $user->activated_at;
            $user->updated_by = $request->user()->id;
            $user->save();

            UserStatusHistory::create([
                'user_id' => $user->id,
                'old_status' => $oldStatus,
                'new_status' => $status,
                'reason' => $validated['reason'] ?? null,
                'changed_by' => $request->user()->id,
                'changed_at' => now(),
            ]);
        });

        return response()->json([
            'data' => $this->serializeUser($user->fresh($this->userRelations())),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateUserPayload(Request $request, bool $creating, ?User $user = null): array
    {
        $validated = $request->validate([
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'email' => [$creating ? 'required' : 'sometimes', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => ['sometimes', 'string', 'min:8'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'timezone' => ['sometimes', 'nullable', 'string', Rule::in(timezone_identifiers_list())],
            'profile_photo_path' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'signed_document_path' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'status' => ['sometimes', 'string', Rule::in(User::STATUSES)],
            'status_reason' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'role' => [$creating ? 'required' : 'sometimes', 'string', Rule::in(self::MANAGED_ROLES)],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
            'student_profile' => ['sometimes', 'array'],
            'student_profile.english_level' => ['sometimes', 'nullable', 'string', 'max:100'],
            'student_profile.course' => ['sometimes', 'nullable', 'string', 'max:255'],
            'student_profile.assigned_teacher_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'student_profile.class_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'student_profile.start_date' => ['sometimes', 'nullable', 'date'],
            'student_profile.notes' => ['sometimes', 'nullable', 'string'],
            'student_profile.preferences' => ['sometimes', 'nullable', 'string'],
            'student_profile.goals' => ['sometimes', 'nullable', 'string'],
            'student_profile.learning_concerns' => ['sometimes', 'nullable', 'string'],
            'teacher_profile' => ['sometimes', 'array'],
            'teacher_profile.specialization' => ['sometimes', 'nullable', 'string', 'max:255'],
            'teacher_profile.teaching_availability' => ['sometimes', 'nullable', 'array'],
            'teacher_profile.internal_status' => ['sometimes', 'nullable', 'string', 'max:100'],
            'teacher_profile.teaching_notes' => ['sometimes', 'nullable', 'string'],
            'teacher_profile.document_contract_status' => ['sometimes', 'nullable', 'string', 'max:100'],
            'teacher_profile.assigned_student_ids' => ['sometimes', 'array'],
            'teacher_profile.assigned_student_ids.*' => ['integer', 'exists:users,id'],
            'staff_profile' => ['sometimes', 'array'],
            'staff_profile.department' => ['sometimes', 'nullable', 'string', 'max:255'],
            'staff_profile.access_limitations' => ['sometimes', 'nullable', 'string'],
        ]);

        $role = $validated['role'] ?? $this->primaryRole($user);

        $this->validateProfilePayload($role, $validated);
        $this->validateStaffPermissions($role, $validated['permissions'] ?? null);

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function validateProfilePayload(?string $role, array $validated): void
    {
        if ($role === null) {
            return;
        }

        $profileKeys = [
            'student' => 'student_profile',
            'teacher' => 'teacher_profile',
            'staff' => 'staff_profile',
        ];

        foreach ($profileKeys as $profileRole => $profileKey) {
            if ($profileRole !== $role && array_key_exists($profileKey, $validated)) {
                throw ValidationException::withMessages([
                    $profileKey => "The {$profileKey} field can only be used for {$profileRole} users.",
                ]);
            }
        }

        $assignedTeacherId = $validated['student_profile']['assigned_teacher_id'] ?? null;
        if ($assignedTeacherId !== null && ! User::find($assignedTeacherId)?->hasRole('teacher')) {
            throw ValidationException::withMessages([
                'student_profile.assigned_teacher_id' => 'The assigned teacher must have the teacher role.',
            ]);
        }

        $assignedStudentIds = $validated['teacher_profile']['assigned_student_ids'] ?? [];
        foreach ($assignedStudentIds as $studentId) {
            if (! User::find($studentId)?->hasRole('student')) {
                throw ValidationException::withMessages([
                    'teacher_profile.assigned_student_ids' => 'Assigned students must have the student role.',
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncRoleProfile(User $user, string $role, array $validated): void
    {
        if ($role === 'student') {
            $profile = $validated['student_profile'] ?? [];

            $user->studentProfile()->updateOrCreate(
                ['user_id' => $user->id],
                Arr::only($profile, [
                    'english_level',
                    'course',
                    'assigned_teacher_id',
                    'class_type',
                    'start_date',
                    'notes',
                    'preferences',
                    'goals',
                    'learning_concerns',
                ])
            );
        }

        if ($role === 'teacher') {
            $profile = $validated['teacher_profile'] ?? [];

            $user->teacherProfile()->updateOrCreate(
                ['user_id' => $user->id],
                Arr::only($profile, [
                    'specialization',
                    'teaching_availability',
                    'internal_status',
                    'teaching_notes',
                    'document_contract_status',
                ])
            );

            if (array_key_exists('assigned_student_ids', $profile)) {
                StudentProfile::where('assigned_teacher_id', $user->id)
                    ->when($profile['assigned_student_ids'] !== [], fn ($query) => $query->whereNotIn('user_id', $profile['assigned_student_ids']))
                    ->update(['assigned_teacher_id' => null]);

                StudentProfile::whereIn('user_id', $profile['assigned_student_ids'])
                    ->update(['assigned_teacher_id' => $user->id]);
            }
        }

        if ($role === 'staff') {
            $profile = $validated['staff_profile'] ?? [];

            $user->staffProfile()->updateOrCreate(
                ['user_id' => $user->id],
                Arr::only($profile, [
                    'department',
                    'access_limitations',
                ])
            );
        }
    }

    /**
     * @param  array<int, string>|null  $permissions
     */
    private function validateStaffPermissions(?string $role, ?array $permissions): void
    {
        if ($permissions !== null && $role !== 'staff') {
            throw ValidationException::withMessages([
                'permissions' => 'Custom permissions can only be assigned to staff users.',
            ]);
        }
    }

    /**
     * @param  array<int, string>|null  $permissions
     */
    private function syncStaffPermissions(User $user, string $role, ?array $permissions): void
    {
        if ($role !== 'staff') {
            $user->syncPermissions([]);

            return;
        }

        if ($permissions !== null) {
            $user->syncPermissions(Permission::whereIn('name', $permissions)->get());
        }
    }

    private function primaryRole(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        return $user->roles()->whereIn('name', self::MANAGED_ROLES)->value('name');
    }

    /**
     * @return array<int, string>
     */
    private function directPermissionNames(User $user): array
    {
        return $user->getAllPermissions()->pluck('name')->sort()->values()->all();
    }

    /**
     * @param  array<int, string>  $oldPermissions
     * @param  array<int, string>  $newPermissions
     */
    private function auditAccessControlChanges(
        int $actorUserId,
        User $targetUser,
        ?string $oldRole,
        ?string $newRole,
        array $oldPermissions,
        array $newPermissions,
        ?string $oldStaffAccessLevel,
        ?string $newStaffAccessLevel,
    ): void {
        if ($oldRole !== $newRole) {
            $this->auditLogService->record(
                actorUserId: $actorUserId,
                actionType: AuditActionType::ROLE_UPDATED,
                module: AuditModule::USERS,
                targetEntityType: 'user',
                targetEntityId: $targetUser->id,
                metadata: [
                    'affected_user_id' => $targetUser->id,
                    'changed_fields' => ['role' => true],
                    'old_role' => $oldRole,
                    'new_role' => $newRole,
                ],
            );
        }

        $addedPermissions = array_values(array_diff($newPermissions, $oldPermissions));
        $removedPermissions = array_values(array_diff($oldPermissions, $newPermissions));

        if ($addedPermissions !== [] || $removedPermissions !== []) {
            $this->auditLogService->record(
                actorUserId: $actorUserId,
                actionType: AuditActionType::PERMISSION_UPDATED,
                module: AuditModule::PERMISSIONS,
                targetEntityType: 'user',
                targetEntityId: $targetUser->id,
                metadata: [
                    'affected_user_id' => $targetUser->id,
                    'changed_fields' => ['permissions' => true],
                    'added_permissions' => array_values($addedPermissions),
                    'removed_permissions' => array_values($removedPermissions),
                ],
            );
        }

        if ($oldStaffAccessLevel !== $newStaffAccessLevel) {
            $this->auditLogService->record(
                actorUserId: $actorUserId,
                actionType: AuditActionType::STAFF_ACCESS_LEVEL_CHANGED,
                module: AuditModule::USERS,
                targetEntityType: 'staff_profile',
                targetEntityId: $targetUser->staffProfile?->id ?? $targetUser->id,
                metadata: [
                    'affected_user_id' => $targetUser->id,
                    'changed_fields' => ['staff_profile.access_limitations' => true],
                    'old_access_level' => $oldStaffAccessLevel,
                    'new_access_level' => $newStaffAccessLevel,
                    'old_role' => $oldRole,
                    'new_role' => $newRole,
                ],
            );
        }
    }

    /**
     * @return array<int, string>
     */
    private function userRelations(): array
    {
        return [
            'roles',
            'permissions',
            'studentProfile.assignedTeacher',
            'teacherProfile',
            'staffProfile',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUser(User $user): array
    {
        $user->loadMissing($this->userRelations());

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'timezone' => $user->timezone,
            'profile_photo_path' => $user->profile_photo_path,
            'signed_document_path' => $user->signed_document_path,
            'status' => $user->status,
            'invited_at' => $user->invited_at,
            'activated_at' => $user->activated_at,
            'created_by' => $user->created_by,
            'updated_by' => $user->updated_by,
            'roles' => $user->roles->pluck('name')->values(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
            'student_profile' => $user->studentProfile,
            'teacher_profile' => $user->teacherProfile,
            'staff_profile' => $user->staffProfile,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }
}
