<?php

namespace App\Services;

use App\Models\CourseType;
use App\Models\LessonRecord;
use App\Services\PortalSettings\PortalSettingsService;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PortalMetadataService
{
    public const CACHE_KEY_COURSE_TYPES = 'tvio:portal_metadata:course_types:v1';

    public const CACHE_KEY_LESSON_TYPES = 'tvio:portal_metadata:lesson_types:v1';

    public const CACHE_KEY_ATTENDANCE_STATUSES = 'tvio:portal_metadata:attendance_status_options:v1';

    public const CACHE_KEY_ROLES_PERMISSIONS = 'tvio:portal_metadata:roles_permissions:v1';

    public const CACHE_TTL_COURSE_TYPES_MINUTES = 60;

    public const CACHE_TTL_STATIC_OPTIONS_HOURS = 24;

    public const CACHE_TTL_ACCESS_METADATA_HOURS = 12;

    public function __construct(private readonly PortalSettingsService $portalSettingsService) {}

    /**
     * Return stable portal metadata used by common app screens.
     *
     * Individual metadata groups are cached by their dedicated methods, and
     * role/permission metadata is included only for authorized callers.
     *
     * @return array<string, mixed>
     */
    public function portalMetadata(bool $includeAccessMetadata = false): array
    {
        $metadata = [
            'public_settings' => $this->portalSettingsService->all(publicOnly: true),
            'course_types' => $this->courseTypes(),
            'lesson_types' => $this->lessonTypes(),
            'attendance_status_options' => $this->attendanceStatusOptions(),
        ];

        if ($includeAccessMetadata) {
            $metadata['access'] = $this->rolePermissionMetadata();
        }

        return $metadata;
    }

    /**
     * Return cached course-type metadata for portal selectors.
     *
     * @return array<int, array<string, mixed>>
     */
    public function courseTypes(): array
    {
        return Cache::remember(
            self::CACHE_KEY_COURSE_TYPES,
            now()->addMinutes(self::CACHE_TTL_COURSE_TYPES_MINUTES),
            fn (): array => CourseType::query()
                ->select(['public_id', 'name', 'slug', 'description', 'sort_order'])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (CourseType $courseType): array => [
                    'id' => $courseType->public_id,
                    'name' => $courseType->name,
                    'slug' => $courseType->slug,
                    'description' => $courseType->description,
                    'sort_order' => $courseType->sort_order,
                ])
                ->values()
                ->all()
        );
    }

    /**
     * Return cached static lesson-type options.
     *
     * @return array<int, array{key: string, label: string}>
     */
    public function lessonTypes(): array
    {
        return Cache::remember(
            self::CACHE_KEY_LESSON_TYPES,
            now()->addHours(self::CACHE_TTL_STATIC_OPTIONS_HOURS),
            fn (): array => collect(LessonRecord::LESSON_TYPES)
                ->map(fn (string $type): array => [
                    'key' => $type,
                    'label' => $this->label($type),
                ])
                ->values()
                ->all()
        );
    }

    /**
     * Return cached attendance-status options from portal settings or defaults.
     *
     * @return array<string, mixed>
     */
    public function attendanceStatusOptions(): array
    {
        return Cache::remember(
            self::CACHE_KEY_ATTENDANCE_STATUSES,
            now()->addHours(self::CACHE_TTL_STATIC_OPTIONS_HOURS),
            function (): array {
                $settings = $this->portalSettingsService->value('attendance.status_options');

                if (is_array($settings) && is_array($settings['statuses'] ?? null)) {
                    return [
                        'default_status' => $settings['default_status'] ?? null,
                        'statuses' => array_values($settings['statuses']),
                    ];
                }

                return [
                    'default_status' => LessonRecord::ATTENDANCE_PRESENT,
                    'statuses' => collect(LessonRecord::ATTENDANCE_STATUSES)
                        ->map(fn (string $status): array => [
                            'key' => $status,
                            'label' => $this->label($status),
                            'counts_as_attended' => in_array($status, [
                                LessonRecord::ATTENDANCE_PRESENT,
                                LessonRecord::ATTENDANCE_LATE,
                            ], true),
                        ])
                        ->values()
                        ->all(),
                ];
            }
        );
    }

    /**
     * Return cached role and permission metadata for access-management screens.
     *
     * @return array{roles: array<int, array<string, mixed>>, permissions: array<int, array<string, string>>}
     */
    public function rolePermissionMetadata(): array
    {
        return Cache::remember(
            self::CACHE_KEY_ROLES_PERMISSIONS,
            now()->addHours(self::CACHE_TTL_ACCESS_METADATA_HOURS),
            fn (): array => [
                'roles' => Role::query()
                    ->with('permissions:id,name')
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (Role $role): array => [
                        'name' => $role->name,
                        'label' => $this->label($role->name),
                        'permissions' => $role->permissions
                            ->pluck('name')
                            ->sort()
                            ->values()
                            ->all(),
                    ])
                    ->values()
                    ->all(),
                'permissions' => Permission::query()
                    ->orderBy('name')
                    ->get(['name'])
                    ->map(fn (Permission $permission): array => [
                        'name' => $permission->name,
                        'label' => $this->label($permission->name),
                    ])
                    ->values()
                    ->all(),
            ]
        );
    }

    /**
     * Clear cached course-type metadata after catalog changes.
     */
    public function forgetCourseTypes(): void
    {
        Cache::forget(self::CACHE_KEY_COURSE_TYPES);
    }

    /**
     * Clear cached attendance options after the backing portal setting changes.
     */
    public function forgetAttendanceStatusOptions(): void
    {
        Cache::forget(self::CACHE_KEY_ATTENDANCE_STATUSES);
    }

    /**
     * Clear cached access metadata after role or permission changes.
     */
    public function forgetRolePermissionMetadata(): void
    {
        Cache::forget(self::CACHE_KEY_ROLES_PERMISSIONS);
    }

    /**
     * Clear all portal metadata cache entries owned by this service.
     */
    public function forgetAll(): void
    {
        $this->forgetCourseTypes();
        $this->forgetAttendanceStatusOptions();
        $this->forgetRolePermissionMetadata();
        Cache::forget(self::CACHE_KEY_LESSON_TYPES);
    }

    private function label(string $value): string
    {
        return str($value)
            ->replace(['.', '_', '-'], ' ')
            ->title()
            ->toString();
    }
}
