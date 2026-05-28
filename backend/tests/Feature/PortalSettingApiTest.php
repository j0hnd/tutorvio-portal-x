<?php

namespace Tests\Feature;

use App\Enums\AuditActionType;
use App\Enums\AuditModule;
use App\Models\PortalSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PortalSettingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_view_all_portal_settings_with_defaults(): void
    {
        Sanctum::actingAs($this->createRoleUser('admin'));

        $this->getJson('/api/v1/admin/portal-settings')
            ->assertOk()
            ->assertJsonPath('data.0.key', 'school.profile')
            ->assertJsonPath('data.1.key', 'portal.default_timezone')
            ->assertJsonPath('data.1.value', 'Asia/Manila')
            ->assertJsonPath('data.1.is_public', true)
            ->assertJsonPath('allowed_keys.0', 'school.profile');
    }

    public function test_staff_requires_view_or_manage_permission_for_admin_portal_settings(): void
    {
        $staff = $this->createRoleUser('staff');
        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/admin/portal-settings')->assertForbidden();
        $this->patchJson('/api/v1/admin/portal-settings', [
            'settings' => ['portal.default_timezone' => 'UTC'],
        ])->assertForbidden();

        $staff->givePermissionTo('portal_settings.view');
        $this->getJson('/api/v1/admin/portal-settings')->assertOk();

        $this->patchJson('/api/v1/admin/portal-settings', [
            'settings' => ['portal.default_timezone' => 'UTC'],
        ])->assertForbidden();

        $staff->givePermissionTo('portal_settings.manage');
        $this->patchJson('/api/v1/admin/portal-settings', [
            'settings' => ['portal.default_timezone' => 'UTC'],
        ])->assertOk();
    }

    public function test_admin_can_update_allowed_settings_and_actor_is_stored_and_audited(): void
    {
        $admin = $this->createRoleUser('admin');
        Sanctum::actingAs($admin);

        $this->patchJson('/api/v1/admin/portal-settings', [
            'settings' => [
                'portal.default_timezone' => 'UTC',
                'scheduling.schedule_change_requires_approval' => false,
                'scheduling.class_cancellation_rules' => [
                    'minimum_notice_hours' => 6,
                    'requires_reason' => true,
                    'charge_late_cancellation' => true,
                    'allowed_requester_roles' => ['student', 'staff'],
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.key', 'portal.default_timezone')
            ->assertJsonPath('data.0.value', 'UTC')
            ->assertJsonPath('data.0.updated_by', $admin->id)
            ->assertJsonPath('data.1.value', false);

        $this->assertDatabaseHas('portal_settings', [
            'key' => 'portal.default_timezone',
            'updated_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action_type' => AuditActionType::PORTAL_SETTING_UPDATED->value,
            'module' => AuditModule::PORTAL_SETTINGS->value,
            'target_entity_type' => 'portal_setting',
        ]);
    }

    public function test_unknown_setting_keys_are_rejected(): void
    {
        Sanctum::actingAs($this->createRoleUser('admin'));

        $this->patchJson('/api/v1/admin/portal-settings', [
            'settings' => [
                'app.debug' => true,
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('settings');

        $this->assertDatabaseMissing('portal_settings', [
            'key' => 'app.debug',
        ]);
    }

    public function test_setting_values_are_validated_by_key(): void
    {
        Sanctum::actingAs($this->createRoleUser('admin'));

        $this->patchJson('/api/v1/admin/portal-settings', [
            'settings' => [
                'scheduling.class_cancellation_rules' => [
                    'minimum_notice_hours' => -1,
                    'requires_reason' => true,
                    'charge_late_cancellation' => false,
                    'allowed_requester_roles' => ['owner'],
                ],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'value.minimum_notice_hours',
                'value.allowed_requester_roles.0',
            ]);
    }

    public function test_authenticated_users_only_read_public_safe_settings(): void
    {
        $admin = $this->createRoleUser('admin');
        PortalSetting::query()->create([
            'key' => 'school.profile',
            'category' => 'school',
            'value' => ['name' => 'Tutorvio School'],
            'value_type' => PortalSetting::TYPE_JSON,
            'description' => 'School profile',
            'is_public' => true,
            'updated_by' => $admin->id,
        ]);
        PortalSetting::query()->create([
            'key' => 'notifications.preferences',
            'category' => 'notifications',
            'value' => [
                'channels' => ['database'],
                'reminder_minutes' => [60],
                'billing_notifications_enabled' => true,
                'schedule_notifications_enabled' => true,
            ],
            'value_type' => PortalSetting::TYPE_JSON,
            'description' => 'Notification preferences',
            'is_public' => false,
            'updated_by' => $admin->id,
        ]);

        Sanctum::actingAs($this->createRoleUser('student'));

        $this->getJson('/api/v1/portal-settings')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.key', 'school.profile')
            ->assertJsonPath('data.0.value.name', 'Tutorvio School')
            ->assertJsonPath('data.1.key', 'portal.default_timezone')
            ->assertJsonMissing(['key' => 'notifications.preferences'])
            ->assertJsonMissingPath('data.0.updated_by')
            ->assertJsonMissingPath('data.0.updated_by_user');
    }

    public function test_non_admin_roles_cannot_modify_portal_settings(): void
    {
        Sanctum::actingAs($this->createRoleUser('teacher'));

        $this->patchJson('/api/v1/admin/portal-settings', [
            'settings' => ['portal.default_timezone' => 'UTC'],
        ])->assertForbidden();
    }

    private function createRoleUser(string $role): User
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $user->assignRole($role);

        return $user;
    }
}
