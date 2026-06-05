<?php

namespace Tests\Feature;

use App\Models\CourseType;
use App\Models\PortalSetting;
use App\Models\User;
use App\Services\PortalMetadataService;
use App\Services\PortalSettings\PortalSettingsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PortalMetadataCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_authenticated_metadata_endpoint_returns_cached_common_metadata(): void
    {
        $admin = $this->roleUser('admin');
        CourseType::factory()->create([
            'name' => 'Business English',
            'slug' => 'business-english',
            'sort_order' => 2,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/metadata')
            ->assertOk()
            ->assertJsonPath('data.course_types.0.name', 'Business English')
            ->assertJsonPath('data.lesson_types.0.key', 'trial_class')
            ->assertJsonPath('data.attendance_status_options.default_status', 'scheduled')
            ->assertJsonPath('data.public_settings.0.key', 'school.profile')
            ->assertJsonPath('data.access.roles.0.name', 'admin')
            ->assertJsonPath('data.access.permissions.0.name', 'academic_records.manage');

        $this->assertTrue(Cache::has(PortalSettingsService::CACHE_KEY_PUBLIC));
        $this->assertTrue(Cache::has(PortalMetadataService::CACHE_KEY_COURSE_TYPES));
        $this->assertTrue(Cache::has(PortalMetadataService::CACHE_KEY_LESSON_TYPES));
        $this->assertTrue(Cache::has(PortalMetadataService::CACHE_KEY_ATTENDANCE_STATUSES));
        $this->assertTrue(Cache::has(PortalMetadataService::CACHE_KEY_ROLES_PERMISSIONS));
    }

    public function test_non_privileged_metadata_endpoint_omits_role_and_permission_metadata(): void
    {
        Sanctum::actingAs($this->roleUser('student'));

        $this->getJson('/api/v1/metadata')
            ->assertOk()
            ->assertJsonMissingPath('data.access')
            ->assertJsonPath('data.lesson_types.1.key', 'first_official_lesson');
    }

    public function test_course_type_metadata_is_returned_from_cache_on_subsequent_reads(): void
    {
        CourseType::factory()->create([
            'name' => 'Cached English',
            'slug' => 'cached-english',
        ]);

        $metadata = app(PortalMetadataService::class);

        $this->assertSame('Cached English', $metadata->courseTypes()[0]['name']);
        $this->assertTrue(Cache::has(PortalMetadataService::CACHE_KEY_COURSE_TYPES));

        CourseType::withoutEvents(fn () => CourseType::query()->delete());

        $this->assertSame('Cached English', $metadata->courseTypes()[0]['name']);

        $metadata->forgetCourseTypes();

        $this->assertSame([], $metadata->courseTypes());
    }

    public function test_public_portal_settings_are_returned_from_cache_on_subsequent_reads(): void
    {
        $settings = app(PortalSettingsService::class);

        $this->assertSame(
            'Asia/Manila',
            collect($settings->all(publicOnly: true))->firstWhere('key', 'portal.default_timezone')['value']
        );
        $this->assertTrue(Cache::has(PortalSettingsService::CACHE_KEY_PUBLIC));

        PortalSetting::withoutEvents(fn () => PortalSetting::query()->create([
            'key' => 'portal.default_timezone',
            'category' => 'portal',
            'value' => 'UTC',
            'value_type' => PortalSetting::TYPE_STRING,
            'description' => 'Default timezone',
            'is_public' => true,
        ]));

        $this->assertSame(
            'Asia/Manila',
            collect($settings->all(publicOnly: true))->firstWhere('key', 'portal.default_timezone')['value']
        );

        $settings->forgetCachedSettings();

        $this->assertSame(
            'UTC',
            collect($settings->all(publicOnly: true))->firstWhere('key', 'portal.default_timezone')['value']
        );
    }

    public function test_portal_settings_fall_back_to_source_when_cache_read_fails(): void
    {
        PortalSetting::query()->create([
            'key' => 'portal.default_timezone',
            'category' => 'portal',
            'value' => 'UTC',
            'value_type' => PortalSetting::TYPE_STRING,
            'description' => 'Default timezone',
            'is_public' => true,
        ]);

        Log::shouldReceive('warning')->once();
        Cache::shouldReceive('get')->once()->andThrow(new \RuntimeException('cache unavailable'));

        $settings = app(PortalSettingsService::class)->all(publicOnly: true);

        $this->assertSame('UTC', collect($settings)->firstWhere('key', 'portal.default_timezone')['value']);
    }

    public function test_course_type_metadata_falls_back_to_database_when_cache_read_fails(): void
    {
        CourseType::factory()->create([
            'name' => 'Direct DB English',
            'slug' => 'direct-db-english',
        ]);

        Log::shouldReceive('warning')->once();
        Cache::shouldReceive('get')->once()->andThrow(new \RuntimeException('cache unavailable'));

        $metadata = app(PortalMetadataService::class)->courseTypes();

        $this->assertSame('Direct DB English', $metadata[0]['name']);
    }

    public function test_course_type_metadata_cache_is_invalidated_when_course_types_change(): void
    {
        CourseType::factory()->create(['name' => 'General English', 'slug' => 'general-english']);

        $metadata = app(PortalMetadataService::class);
        $this->assertCount(1, $metadata->courseTypes());
        $this->assertTrue(Cache::has(PortalMetadataService::CACHE_KEY_COURSE_TYPES));

        CourseType::factory()->create(['name' => 'Exam Preparation', 'slug' => 'exam-preparation']);

        $this->assertFalse(Cache::has(PortalMetadataService::CACHE_KEY_COURSE_TYPES));
        $this->assertCount(2, $metadata->courseTypes());
    }

    public function test_portal_setting_cache_is_invalidated_when_settings_change(): void
    {
        $settings = app(PortalSettingsService::class);

        $this->assertSame('Asia/Manila', $settings->value('portal.default_timezone'));
        $this->assertNotEmpty($settings->all(publicOnly: true));
        $this->assertTrue(Cache::has(PortalSettingsService::CACHE_KEY_PUBLIC));

        PortalSetting::query()->create([
            'key' => 'portal.default_timezone',
            'category' => 'portal',
            'value' => 'UTC',
            'value_type' => PortalSetting::TYPE_STRING,
            'description' => 'Default timezone',
            'is_public' => true,
        ]);

        $this->assertFalse(Cache::has(PortalSettingsService::CACHE_KEY_PUBLIC));
        $this->assertSame('UTC', $settings->value('portal.default_timezone'));
    }

    public function test_attendance_status_metadata_cache_is_invalidated_when_setting_changes(): void
    {
        $metadata = app(PortalMetadataService::class);

        $this->assertSame('scheduled', $metadata->attendanceStatusOptions()['default_status']);
        $this->assertTrue(Cache::has(PortalMetadataService::CACHE_KEY_ATTENDANCE_STATUSES));

        PortalSetting::query()->create([
            'key' => 'attendance.status_options',
            'category' => 'attendance',
            'value' => [
                'default_status' => 'present',
                'statuses' => [
                    ['key' => 'present', 'label' => 'Present', 'counts_as_attended' => true],
                ],
            ],
            'value_type' => PortalSetting::TYPE_JSON,
            'description' => 'Attendance statuses',
            'is_public' => true,
        ]);

        $this->assertFalse(Cache::has(PortalMetadataService::CACHE_KEY_ATTENDANCE_STATUSES));
        $this->assertSame('present', $metadata->attendanceStatusOptions()['default_status']);
    }

    public function test_role_permission_metadata_cache_is_invalidated_when_permissions_change(): void
    {
        $metadata = app(PortalMetadataService::class);

        $this->assertNotEmpty($metadata->rolePermissionMetadata()['permissions']);
        $this->assertTrue(Cache::has(PortalMetadataService::CACHE_KEY_ROLES_PERMISSIONS));

        Permission::query()->create([
            'name' => 'metadata.example',
            'guard_name' => 'web',
        ]);

        $this->assertFalse(Cache::has(PortalMetadataService::CACHE_KEY_ROLES_PERMISSIONS));
        $this->assertContains(
            'metadata.example',
            collect($metadata->rolePermissionMetadata()['permissions'])->pluck('name')->all()
        );
    }

    private function roleUser(string $role): User
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $user->assignRole($role);

        return $user;
    }
}
