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

        $this->getJson('/api/admin/settings')
            ->assertOk()
            ->assertJsonPath('data.0.key', 'school.profile')
            ->assertJsonPath('data.1.key', 'portal.default_timezone')
            ->assertJsonPath('data.1.value', 'Asia/Manila')
            ->assertJsonPath('data.1.is_public', true)
            ->assertJsonPath('data.2.key', 'school.branding')
            ->assertJsonPath('data.3.key', 'lessons.defaults')
            ->assertJsonPath('data.3.value.duration_minutes', 50)
            ->assertJsonPath('data.3.is_public', false)
            ->assertJsonPath('allowed_keys.0', 'school.profile')
            ->assertJsonPath('allowed_keys.10', 'attendance.status_options')
            ->assertJsonPath('allowed_keys.13', 'localization.options');
    }

    public function test_staff_requires_view_or_manage_permission_for_admin_portal_settings(): void
    {
        $staff = $this->createRoleUser('staff');
        Sanctum::actingAs($staff);

        $this->getJson('/api/admin/settings')->assertForbidden();
        $this->patchJson('/api/admin/settings', [
            'settings' => ['portal.default_timezone' => 'UTC'],
        ])->assertForbidden();

        $staff->givePermissionTo('portal_settings.view');
        $this->getJson('/api/admin/settings')->assertOk();

        $this->patchJson('/api/admin/settings', [
            'settings' => ['portal.default_timezone' => 'UTC'],
        ])->assertForbidden();

        $staff->givePermissionTo('portal_settings.manage');
        $this->patchJson('/api/admin/settings', [
            'settings' => ['portal.default_timezone' => 'UTC'],
        ])->assertOk();
    }

    public function test_admin_can_update_allowed_settings_and_actor_is_stored_and_audited(): void
    {
        $admin = $this->createRoleUser('admin');
        Sanctum::actingAs($admin);

        $this->putJson('/api/admin/settings', [
            'settings' => [
                'portal.default_timezone' => ' utc ',
                'scheduling.schedule_change_requires_approval' => false,
                'scheduling.class_cancellation_rules' => [
                    'minimum_notice_hours' => 6,
                    'requires_reason' => true,
                    'charge_late_cancellation' => true,
                    'allowed_requester_roles' => ['student', 'staff'],
                ],
                'school.branding' => [
                    'primary_color' => '#AABBCC',
                    'secondary_color' => '#0F766E',
                    'accent_color' => '#F59E0B',
                    'logo_url' => 'https://example.com/logo.png',
                    'favicon_url' => null,
                    'support_email' => 'support@example.com',
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.key', 'portal.default_timezone')
            ->assertJsonPath('data.0.value', 'UTC')
            ->assertJsonPath('data.0.updated_by', $admin->id)
            ->assertJsonPath('data.1.value', false)
            ->assertJsonPath('data.3.value.primary_color', '#aabbcc')
            ->assertJsonPath('data.3.value.secondary_color', '#0f766e')
            ->assertJsonPath('data.3.value.accent_color', '#f59e0b');

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

        $this->patchJson('/api/admin/settings', [
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

        $this->patchJson('/api/admin/settings', [
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

        $this->patchJson('/api/admin/settings', [
            'settings' => [
                'school.branding' => [
                    'primary_color' => 'blue',
                    'secondary_color' => '#0f766e',
                    'accent_color' => '#f59e0b',
                    'logo_url' => null,
                    'favicon_url' => null,
                    'support_email' => 'support@example.com',
                ],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('value.primary_color');
    }

    public function test_admin_settings_reject_unsafe_content_and_invalid_asset_references(): void
    {
        Sanctum::actingAs($this->createRoleUser('admin'));

        $this->patchJson('/api/admin/settings', [
            'settings' => [
                'school.branding' => [
                    'primary_color' => '#1d4ed8',
                    'secondary_color' => '#0f766e',
                    'accent_color' => '#f59e0b',
                    'logo_url' => 'javascript:alert(1)',
                    'favicon_url' => '/assets/icons/favicon.ico',
                    'support_email' => 'support@example.com',
                ],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('value.logo_url')
            ->assertJsonMissing(['exception'])
            ->assertJsonMissing(['trace']);

        $this->patchJson('/api/admin/settings', [
            'settings' => [
                'email.templates' => [
                    'sender_name' => 'Tutorvio',
                    'reply_to' => null,
                    'templates' => [
                        'welcome' => '<script>alert(1)</script>',
                        'lesson_reminder' => 'mail.lesson_reminder',
                        'invoice' => 'mail.invoice',
                        'password_reset' => 'mail.password_reset',
                    ],
                ],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('value.templates.welcome')
            ->assertJsonMissing(['exception'])
            ->assertJsonMissing(['trace']);

        $this->assertDatabaseMissing('portal_settings', [
            'key' => 'school.branding',
        ]);
        $this->assertDatabaseMissing('portal_settings', [
            'key' => 'email.templates',
        ]);
    }

    public function test_admin_settings_allow_safe_portal_asset_paths(): void
    {
        Sanctum::actingAs($this->createRoleUser('admin'));

        $this->patchJson('/api/admin/settings', [
            'settings' => [
                'school.branding' => [
                    'primary_color' => '#1d4ed8',
                    'secondary_color' => '#0f766e',
                    'accent_color' => '#f59e0b',
                    'logo_url' => '/assets/branding/logo.svg',
                    'favicon_url' => '/assets/branding/favicon.ico',
                    'support_email' => 'support@example.com',
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.0.value.logo_url', '/assets/branding/logo.svg')
            ->assertJsonPath('data.0.value.favicon_url', '/assets/branding/favicon.ico');
    }

    public function test_admin_settings_reject_unknown_nested_fields_and_duplicate_options(): void
    {
        Sanctum::actingAs($this->createRoleUser('admin'));

        $this->patchJson('/api/admin/settings', [
            'settings' => [
                'courses.settings' => [
                    'default_session_count' => 8,
                    'allow_self_enrollment' => false,
                    'require_staff_assignment' => true,
                    'default_visibility' => 'published',
                    'archive_completed_after_days' => 365,
                    'metadata' => ['unsafe' => true],
                ],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('value');

        $this->patchJson('/api/admin/settings', [
            'settings' => [
                'notifications.preferences' => [
                    'channels' => ['database', 'email', 'email'],
                    'reminder_minutes' => [1440, 60, 60],
                    'billing_notifications_enabled' => true,
                    'schedule_notifications_enabled' => true,
                ],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'value.channels',
                'value.reminder_minutes',
            ]);
    }

    public function test_admin_settings_update_normalizes_supported_setting_values(): void
    {
        Sanctum::actingAs($this->createRoleUser('admin'));

        $this->patchJson('/api/admin/settings', [
            'lessons.defaults' => [
                'duration_minutes' => '45',
                'buffer_minutes' => '5',
                'allow_back_to_back' => '0',
                'default_delivery_mode' => 'online',
            ],
            'attendance.status_options' => [
                'default_status' => ' Present ',
                'statuses' => [
                    ['key' => ' Present ', 'label' => ' Present ', 'counts_as_attended' => '1'],
                    ['key' => 'no show', 'label' => ' No Show ', 'counts_as_attended' => false],
                ],
            ],
            'localization.options' => [
                'default_locale' => 'EN_us',
                'supported_locales' => ['EN_us', 'fil'],
                'date_format' => 'Y-m-d',
                'time_format' => 'H:i',
                'first_day_of_week' => '1',
            ],
            'calendar.color_coding' => [
                'scheduled' => '#ABCDEF',
                'completed' => '#16A34A',
                'cancelled' => '#DC2626',
                'pending' => '#D97706',
                'unavailable' => '#6B7280',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.0.value.duration_minutes', 45)
            ->assertJsonPath('data.0.value.buffer_minutes', 5)
            ->assertJsonPath('data.0.value.allow_back_to_back', false)
            ->assertJsonPath('data.1.value.default_status', 'present')
            ->assertJsonPath('data.1.value.statuses.0.key', 'present')
            ->assertJsonPath('data.1.value.statuses.0.label', 'Present')
            ->assertJsonPath('data.1.value.statuses.1.key', 'no_show')
            ->assertJsonPath('data.2.value.default_locale', 'en-US')
            ->assertJsonPath('data.2.value.supported_locales.0', 'en-US')
            ->assertJsonPath('data.2.value.supported_locales.1', 'fil')
            ->assertJsonPath('data.2.value.first_day_of_week', 1)
            ->assertJsonPath('data.3.value.scheduled', '#abcdef')
            ->assertJsonPath('data.3.value.unavailable', '#6b7280');
    }

    public function test_admin_settings_rejects_invalid_normalized_option_relationships(): void
    {
        Sanctum::actingAs($this->createRoleUser('admin'));

        $this->patchJson('/api/admin/settings', [
            'settings' => [
                'attendance.status_options' => [
                    'default_status' => 'absent',
                    'statuses' => [
                        ['key' => 'present', 'label' => 'Present', 'counts_as_attended' => true],
                    ],
                ],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('value.default_status');

        $this->patchJson('/api/admin/settings', [
            'settings' => [
                'localization.options' => [
                    'default_locale' => 'en',
                    'supported_locales' => ['fil'],
                    'date_format' => 'Y-m-d',
                    'time_format' => 'H:i',
                    'first_day_of_week' => 1,
                ],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('value.default_locale');
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
            ->assertJsonPath('data.0.key', 'school.profile')
            ->assertJsonPath('data.0.value.name', 'Tutorvio School')
            ->assertJsonPath('data.1.key', 'portal.default_timezone')
            ->assertJsonPath('data.2.key', 'school.branding')
            ->assertJsonPath('data.4.key', 'localization.options')
            ->assertJsonMissing(['key' => 'notifications.preferences'])
            ->assertJsonMissing(['key' => 'lessons.defaults'])
            ->assertJsonMissing(['key' => 'email.templates'])
            ->assertJsonMissing(['key' => 'user_roles.defaults'])
            ->assertJsonMissingPath('data.0.updated_by')
            ->assertJsonMissingPath('data.0.updated_by_user');
    }

    public function test_non_admin_roles_cannot_modify_portal_settings(): void
    {
        Sanctum::actingAs($this->createRoleUser('teacher'));

        $this->patchJson('/api/admin/settings', [
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
