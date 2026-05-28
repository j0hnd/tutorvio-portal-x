<?php

namespace App\Services\PortalSettings;

use App\Enums\AuditActionType;
use App\Enums\AuditModule;
use App\Models\PortalSetting;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PortalSettingsService
{
    /**
     * @var array<string, array{category: string, value_type: string, description: string, is_public: bool, default: mixed}>
     */
    private const DEFINITIONS = [
        'school.profile' => [
            'category' => 'school',
            'value_type' => PortalSetting::TYPE_JSON,
            'description' => 'Public-safe school profile details shown in the portal.',
            'is_public' => true,
            'default' => [
                'name' => null,
                'legal_name' => null,
                'email' => null,
                'phone' => null,
                'website' => null,
                'address' => null,
                'logo_url' => null,
            ],
        ],
        'portal.default_timezone' => [
            'category' => 'portal',
            'value_type' => PortalSetting::TYPE_STRING,
            'description' => 'Default timezone for scheduling and portal date display.',
            'is_public' => true,
            'default' => 'Asia/Manila',
        ],
        'scheduling.class_cancellation_rules' => [
            'category' => 'scheduling',
            'value_type' => PortalSetting::TYPE_JSON,
            'description' => 'Operational rules for class cancellations.',
            'is_public' => false,
            'default' => [
                'minimum_notice_hours' => 24,
                'requires_reason' => true,
                'charge_late_cancellation' => false,
                'allowed_requester_roles' => ['student', 'teacher', 'staff', 'admin'],
            ],
        ],
        'scheduling.schedule_change_requires_approval' => [
            'category' => 'scheduling',
            'value_type' => PortalSetting::TYPE_BOOLEAN,
            'description' => 'Whether schedule change requests require staff approval.',
            'is_public' => false,
            'default' => true,
        ],
        'attendance.absence_reporting_rules' => [
            'category' => 'attendance',
            'value_type' => PortalSetting::TYPE_JSON,
            'description' => 'Rules for reporting absences.',
            'is_public' => false,
            'default' => [
                'enabled' => true,
                'minimum_notice_hours' => 12,
                'allow_student_report' => true,
                'allow_teacher_report' => true,
                'required_fields' => ['reason'],
            ],
        ],
        'notifications.preferences' => [
            'category' => 'notifications',
            'value_type' => PortalSetting::TYPE_JSON,
            'description' => 'Default notification behavior for operational events.',
            'is_public' => false,
            'default' => [
                'channels' => ['database', 'email'],
                'reminder_minutes' => [1440, 60],
                'billing_notifications_enabled' => true,
                'schedule_notifications_enabled' => true,
            ],
        ],
        'issues.tracking_configuration' => [
            'category' => 'issues',
            'value_type' => PortalSetting::TYPE_JSON,
            'description' => 'Issue tracking defaults and enabled categories.',
            'is_public' => false,
            'default' => [
                'enabled' => true,
                'default_priority' => 'normal',
                'categories' => ['technical_issue', 'class_incident', 'student_concern', 'teacher_concern'],
                'internal_comments_enabled' => true,
            ],
        ],
        'academic_records.settings' => [
            'category' => 'academic_records',
            'value_type' => PortalSetting::TYPE_JSON,
            'description' => 'Academic record visibility, approval, and retention settings.',
            'is_public' => false,
            'default' => [
                'require_teacher_approval' => false,
                'visible_to_students' => true,
                'retention_years' => 7,
                'allowed_record_types' => ['progress', 'attendance', 'assessment', 'note', 'certificate'],
            ],
        ],
    ];

    public function __construct(private readonly AuditLogService $auditLogService) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(bool $publicOnly = false): array
    {
        $definitions = collect(self::DEFINITIONS)
            ->when($publicOnly, fn ($items) => $items->filter(fn (array $definition) => $definition['is_public']));

        $stored = PortalSetting::query()
            ->whereIn('key', $definitions->keys()->all())
            ->with('updatedBy')
            ->get()
            ->keyBy('key');

        return $definitions
            ->map(fn (array $definition, string $key) => $this->formatSetting(
                $key,
                $definition,
                $stored->get($key),
                includeAdminMetadata: ! $publicOnly
            ))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, array<string, mixed>>
     */
    public function update(array $settings, User $actor): array
    {
        $unknownKeys = array_values(array_diff(array_keys($settings), array_keys(self::DEFINITIONS)));

        if ($unknownKeys !== []) {
            throw ValidationException::withMessages([
                'settings' => 'Unsupported portal setting keys: '.implode(', ', $unknownKeys).'.',
            ]);
        }

        $updated = DB::transaction(function () use ($settings, $actor) {
            $updated = [];

            foreach ($settings as $key => $value) {
                $definition = self::DEFINITIONS[$key];
                $validatedValue = $this->validateValue($key, $value);

                $setting = PortalSetting::query()->updateOrCreate(
                    ['key' => $key],
                    [
                        'category' => $definition['category'],
                        'value' => $validatedValue,
                        'value_type' => $definition['value_type'],
                        'description' => $definition['description'],
                        'is_public' => $definition['is_public'],
                        'updated_by' => $actor->id,
                    ]
                );

                $this->auditLogService->record(
                    actorUserId: $actor->id,
                    actionType: AuditActionType::PORTAL_SETTING_UPDATED,
                    module: AuditModule::PORTAL_SETTINGS,
                    targetEntityType: 'portal_setting',
                    targetEntityId: $setting->id,
                    metadata: [
                        'key' => $key,
                        'category' => $definition['category'],
                    ]
                );

                $updated[] = $this->formatSetting($key, $definition, $setting->refresh()->load('updatedBy'));
            }

            return $updated;
        });

        return $updated;
    }

    /**
     * @return array<int, string>
     */
    public function allowedKeys(): array
    {
        return array_keys(self::DEFINITIONS);
    }

    public function value(string $key): mixed
    {
        if (! array_key_exists($key, self::DEFINITIONS)) {
            throw ValidationException::withMessages([
                'settings' => 'Unsupported portal setting key: '.$key.'.',
            ]);
        }

        $setting = PortalSetting::query()
            ->where('key', $key)
            ->first();

        return $setting?->value ?? self::DEFINITIONS[$key]['default'];
    }

    private function validateValue(string $key, mixed $value): mixed
    {
        $validator = Validator::make(
            ['value' => $value],
            $this->rulesFor($key),
            [],
            ['value' => $key]
        );

        return $validator->validate()['value'];
    }

    /**
     * @return array<string, mixed>
     */
    private function rulesFor(string $key): array
    {
        return match ($key) {
            'school.profile' => [
                'value' => ['required', 'array:name,legal_name,email,phone,website,address,logo_url'],
                'value.name' => ['nullable', 'string', 'max:160'],
                'value.legal_name' => ['nullable', 'string', 'max:200'],
                'value.email' => ['nullable', 'email', 'max:255'],
                'value.phone' => ['nullable', 'string', 'max:40'],
                'value.website' => ['nullable', 'url', 'max:255'],
                'value.address' => ['nullable', 'string', 'max:500'],
                'value.logo_url' => ['nullable', 'url', 'max:255'],
            ],
            'portal.default_timezone' => [
                'value' => ['required', 'string', 'timezone'],
            ],
            'scheduling.class_cancellation_rules' => [
                'value' => ['required', 'array:minimum_notice_hours,requires_reason,charge_late_cancellation,allowed_requester_roles'],
                'value.minimum_notice_hours' => ['required', 'integer', 'min:0', 'max:720'],
                'value.requires_reason' => ['required', 'boolean'],
                'value.charge_late_cancellation' => ['required', 'boolean'],
                'value.allowed_requester_roles' => ['required', 'array', 'min:1', 'max:4'],
                'value.allowed_requester_roles.*' => ['string', Rule::in(['student', 'teacher', 'staff', 'admin'])],
            ],
            'scheduling.schedule_change_requires_approval' => [
                'value' => ['required', 'boolean'],
            ],
            'attendance.absence_reporting_rules' => [
                'value' => ['required', 'array:enabled,minimum_notice_hours,allow_student_report,allow_teacher_report,required_fields'],
                'value.enabled' => ['required', 'boolean'],
                'value.minimum_notice_hours' => ['required', 'integer', 'min:0', 'max:720'],
                'value.allow_student_report' => ['required', 'boolean'],
                'value.allow_teacher_report' => ['required', 'boolean'],
                'value.required_fields' => ['required', 'array', 'max:4'],
                'value.required_fields.*' => ['string', Rule::in(['reason', 'documentation', 'contact_number', 'makeup_preference'])],
            ],
            'notifications.preferences' => [
                'value' => ['required', 'array:channels,reminder_minutes,billing_notifications_enabled,schedule_notifications_enabled'],
                'value.channels' => ['required', 'array', 'min:1', 'max:3'],
                'value.channels.*' => ['string', Rule::in(['database', 'email', 'sms'])],
                'value.reminder_minutes' => ['required', 'array', 'min:1', 'max:5'],
                'value.reminder_minutes.*' => ['integer', 'min:0', 'max:43200'],
                'value.billing_notifications_enabled' => ['required', 'boolean'],
                'value.schedule_notifications_enabled' => ['required', 'boolean'],
            ],
            'issues.tracking_configuration' => [
                'value' => ['required', 'array:enabled,default_priority,categories,internal_comments_enabled'],
                'value.enabled' => ['required', 'boolean'],
                'value.default_priority' => ['required', 'string', Rule::in(['low', 'normal', 'high', 'urgent'])],
                'value.categories' => ['required', 'array', 'min:1', 'max:8'],
                'value.categories.*' => ['string', Rule::in(['technical_issue', 'class_incident', 'student_concern', 'teacher_concern', 'student_absent_issue_form', 'teacher_absent_issue_form', 'billing_issue', 'content_issue'])],
                'value.internal_comments_enabled' => ['required', 'boolean'],
            ],
            'academic_records.settings' => [
                'value' => ['required', 'array:require_teacher_approval,visible_to_students,retention_years,allowed_record_types'],
                'value.require_teacher_approval' => ['required', 'boolean'],
                'value.visible_to_students' => ['required', 'boolean'],
                'value.retention_years' => ['required', 'integer', 'min:1', 'max:25'],
                'value.allowed_record_types' => ['required', 'array', 'min:1', 'max:8'],
                'value.allowed_record_types.*' => ['string', Rule::in(['progress', 'attendance', 'assessment', 'note', 'certificate', 'placement', 'homework'])],
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function formatSetting(
        string $key,
        array $definition,
        ?PortalSetting $stored,
        bool $includeAdminMetadata = true
    ): array {
        $setting = [
            'key' => $key,
            'category' => $stored?->category ?? $definition['category'],
            'value' => $stored?->value ?? $definition['default'],
            'value_type' => $stored?->value_type ?? $definition['value_type'],
            'description' => $stored?->description ?? $definition['description'],
            'is_public' => $stored?->is_public ?? $definition['is_public'],
        ];

        if (! $includeAdminMetadata) {
            return $setting;
        }

        return [
            ...$setting,
            'updated_by' => $stored?->updated_by,
            'updated_by_user' => $stored?->relationLoaded('updatedBy') && $stored->updatedBy ? [
                'id' => $stored->updatedBy->id,
                'name' => $stored->updatedBy->name,
                'email' => $stored->updatedBy->email,
            ] : null,
            'updated_at' => $stored?->updated_at,
        ];
    }
}
