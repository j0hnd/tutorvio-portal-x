<?php

namespace App\Http\Resources\PortalSettings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicPortalSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $settings = collect($this->resource)->keyBy('key');

        $profile = $this->settingArray($settings, 'school.profile');
        $branding = $this->settingArray($settings, 'school.branding');
        $localization = $this->settingArray($settings, 'localization.options');
        $calendarColors = $this->settingArray($settings, 'calendar.color_coding');
        $timezone = $this->settingString($settings, 'portal.default_timezone', 'UTC');

        return [
            'school' => [
                'name' => $profile['name'] ?? null,
            ],
            'branding' => [
                'primary_color' => $branding['primary_color'] ?? null,
                'secondary_color' => $branding['secondary_color'] ?? null,
                'accent_color' => $branding['accent_color'] ?? null,
                'logo_url' => $branding['logo_url'] ?? null,
                'favicon_url' => $branding['favicon_url'] ?? null,
            ],
            'locale' => [
                'default' => $localization['default_locale'] ?? 'en',
                'supported' => $localization['supported_locales'] ?? ['en'],
            ],
            'timezone' => [
                'value' => $timezone,
                'label' => $this->timezoneLabel($timezone),
            ],
            'calendar_colors' => $this->calendarColors($calendarColors),
        ];
    }

    private function settingValue($settings, string $key): mixed
    {
        $setting = $settings->get($key);

        return is_array($setting) ? ($setting['value'] ?? null) : null;
    }

    private function settingArray($settings, string $key): array
    {
        $value = $this->settingValue($settings, $key);

        return is_array($value) ? $value : [];
    }

    private function settingString($settings, string $key, string $default): string
    {
        $value = $this->settingValue($settings, $key);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    private function timezoneLabel(string $timezone): string
    {
        return str_replace('_', ' ', $timezone);
    }

    /**
     * @param  array<string, string>|null  $colors
     * @return array<string, array{label: string, color: string|null}>
     */
    private function calendarColors(?array $colors): array
    {
        return [
            'scheduled' => [
                'label' => 'Scheduled',
                'color' => $colors['scheduled'] ?? null,
            ],
            'completed' => [
                'label' => 'Completed',
                'color' => $colors['completed'] ?? null,
            ],
            'cancelled' => [
                'label' => 'Cancelled',
                'color' => $colors['cancelled'] ?? null,
            ],
            'pending' => [
                'label' => 'Pending',
                'color' => $colors['pending'] ?? null,
            ],
            'unavailable' => [
                'label' => 'Unavailable',
                'color' => $colors['unavailable'] ?? null,
            ],
        ];
    }
}
