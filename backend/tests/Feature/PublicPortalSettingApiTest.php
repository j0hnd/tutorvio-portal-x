<?php

namespace Tests\Feature;

use App\Models\PortalSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPortalSettingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_settings_endpoint_returns_minimal_safe_portal_configuration(): void
    {
        PortalSetting::query()->create([
            'key' => 'school.profile',
            'category' => 'school',
            'value' => [
                'name' => 'Tutorvio Academy',
                'legal_name' => 'Tutorvio Academy Holdings Inc.',
                'email' => 'ops@example.com',
                'phone' => '+639171234567',
                'website' => 'https://example.com',
                'address' => 'Internal office address',
                'logo_url' => '/assets/branding/profile-logo.svg',
            ],
            'value_type' => PortalSetting::TYPE_JSON,
            'description' => 'School profile',
            'is_public' => true,
        ]);

        PortalSetting::query()->create([
            'key' => 'school.branding',
            'category' => 'school',
            'value' => [
                'primary_color' => '#123456',
                'secondary_color' => '#abcdef',
                'accent_color' => '#f59e0b',
                'logo_url' => '/assets/branding/logo.svg',
                'favicon_url' => '/assets/branding/favicon.ico',
                'support_email' => 'support@example.com',
            ],
            'value_type' => PortalSetting::TYPE_JSON,
            'description' => 'Branding',
            'is_public' => true,
        ]);

        PortalSetting::query()->create([
            'key' => 'portal.default_timezone',
            'category' => 'portal',
            'value' => 'America/New_York',
            'value_type' => PortalSetting::TYPE_STRING,
            'description' => 'Default timezone',
            'is_public' => true,
        ]);

        PortalSetting::query()->create([
            'key' => 'localization.options',
            'category' => 'localization',
            'value' => [
                'default_locale' => 'en-US',
                'supported_locales' => ['en-US', 'fil'],
                'date_format' => 'm/d/Y',
                'time_format' => 'g:i A',
                'first_day_of_week' => 0,
            ],
            'value_type' => PortalSetting::TYPE_JSON,
            'description' => 'Localization',
            'is_public' => true,
        ]);

        PortalSetting::query()->create([
            'key' => 'notifications.rules',
            'category' => 'notifications',
            'value' => [
                'quiet_hours_enabled' => true,
                'quiet_hours_start' => '22:00',
                'quiet_hours_end' => '07:00',
                'digest_enabled' => true,
                'digest_frequency' => 'daily',
                'escalation_minutes' => 1440,
            ],
            'value_type' => PortalSetting::TYPE_JSON,
            'description' => 'Notification rules',
            'is_public' => false,
        ]);

        $this->getJson('/api/settings/public')
            ->assertOk()
            ->assertJsonPath('data.school.name', 'Tutorvio Academy')
            ->assertJsonPath('data.branding.primary_color', '#123456')
            ->assertJsonPath('data.branding.logo_url', '/assets/branding/logo.svg')
            ->assertJsonPath('data.locale.default', 'en-US')
            ->assertJsonPath('data.locale.supported.1', 'fil')
            ->assertJsonPath('data.timezone.value', 'America/New_York')
            ->assertJsonPath('data.timezone.label', 'America/New York')
            ->assertJsonPath('data.calendar_colors.scheduled.label', 'Scheduled')
            ->assertJsonMissingPath('data.school.legal_name')
            ->assertJsonMissingPath('data.school.email')
            ->assertJsonMissingPath('data.school.phone')
            ->assertJsonMissingPath('data.school.address')
            ->assertJsonMissingPath('data.branding.support_email')
            ->assertJsonMissingPath('data.key')
            ->assertJsonMissingPath('data.allowed_keys')
            ->assertJsonMissingPath('data.updated_by')
            ->assertJsonMissingPath('data.updated_at')
            ->assertJsonMissing(['notifications.rules'])
            ->assertJsonMissing(['email.templates'])
            ->assertJsonMissing(['user_roles.defaults'])
            ->assertJsonMissing(['courses.settings']);
    }

    public function test_public_settings_endpoint_is_available_without_authentication(): void
    {
        $this->getJson('/api/settings/public')
            ->assertOk()
            ->assertJsonPath('data.school.name', null)
            ->assertJsonPath('data.locale.default', 'en')
            ->assertJsonPath('data.timezone.value', 'Asia/Manila');
    }

    public function test_public_settings_endpoint_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 60; $attempt++) {
            $this->getJson('/api/settings/public')->assertOk();
        }

        $this->getJson('/api/settings/public')
            ->assertStatus(429)
            ->assertJson([
                'message' => 'Too many requests.',
            ])
            ->assertHeader('Retry-After');
    }
}
