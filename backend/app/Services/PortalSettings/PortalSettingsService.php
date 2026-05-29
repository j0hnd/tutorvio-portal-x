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
        'school.branding' => [
            'category' => 'school',
            'value_type' => PortalSetting::TYPE_JSON,
            'description' => 'Public-safe brand assets and theme tokens used by the portal.',
            'is_public' => true,
            'default' => [
                'primary_color' => '#1d4ed8',
                'secondary_color' => '#0f766e',
                'accent_color' => '#f59e0b',
                'logo_url' => null,
                'favicon_url' => null,
                'support_email' => null,
            ],
        ],
        'lessons.defaults' => [
            'category' => 'lessons',
            'value_type' => PortalSetting::TYPE_JSON,
            'description' => 'Default lesson duration and booking behavior.',
            'is_public' => false,
            'default' => [
                'duration_minutes' => 50,
                'buffer_minutes' => 10,
                'allow_back_to_back' => true,
                'default_delivery_mode' => 'online',
            ],
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
        'notifications.rules' => [
            'category' => 'notifications',
            'value_type' => PortalSetting::TYPE_JSON,
            'description' => 'Internal notification routing, digest, and escalation rules.',
            'is_public' => false,
            'default' => [
                'quiet_hours_enabled' => true,
                'quiet_hours_start' => '22:00',
                'quiet_hours_end' => '07:00',
                'digest_enabled' => true,
                'digest_frequency' => 'daily',
                'escalation_minutes' => 1440,
            ],
        ],
        'email.templates' => [
            'category' => 'email',
            'value_type' => PortalSetting::TYPE_JSON,
            'description' => 'Internal email template references and sender defaults.',
            'is_public' => false,
            'default' => [
                'sender_name' => 'Tutorvio',
                'reply_to' => null,
                'templates' => [
                    'welcome' => null,
                    'lesson_reminder' => null,
                    'invoice' => null,
                    'password_reset' => null,
                ],
            ],
        ],
        'attendance.status_options' => [
            'category' => 'attendance',
            'value_type' => PortalSetting::TYPE_JSON,
            'description' => 'Public-safe attendance statuses available for display.',
            'is_public' => true,
            'default' => [
                'default_status' => 'scheduled',
                'statuses' => [
                    ['key' => 'scheduled', 'label' => 'Scheduled', 'counts_as_attended' => false],
                    ['key' => 'present', 'label' => 'Present', 'counts_as_attended' => true],
                    ['key' => 'late', 'label' => 'Late', 'counts_as_attended' => true],
                    ['key' => 'absent', 'label' => 'Absent', 'counts_as_attended' => false],
                    ['key' => 'excused', 'label' => 'Excused', 'counts_as_attended' => false],
                ],
            ],
        ],
        'user_roles.defaults' => [
            'category' => 'users',
            'value_type' => PortalSetting::TYPE_JSON,
            'description' => 'Internal defaults used when creating portal users and assigning roles.',
            'is_public' => false,
            'default' => [
                'student_role' => 'student',
                'teacher_role' => 'teacher',
                'staff_role' => 'staff',
                'require_email_verification' => true,
                'auto_activate_invited_users' => false,
            ],
        ],
        'courses.settings' => [
            'category' => 'courses',
            'value_type' => PortalSetting::TYPE_JSON,
            'description' => 'Internal course catalog, enrollment, and archival defaults.',
            'is_public' => false,
            'default' => [
                'default_session_count' => 8,
                'allow_self_enrollment' => false,
                'require_staff_assignment' => true,
                'default_visibility' => 'published',
                'archive_completed_after_days' => 365,
            ],
        ],
        'localization.options' => [
            'category' => 'localization',
            'value_type' => PortalSetting::TYPE_JSON,
            'description' => 'Public-safe locale and language options.',
            'is_public' => true,
            'default' => [
                'default_locale' => 'en',
                'supported_locales' => ['en'],
                'date_format' => 'Y-m-d',
                'time_format' => 'H:i',
                'first_day_of_week' => 1,
            ],
        ],
        'calendar.color_coding' => [
            'category' => 'calendar',
            'value_type' => PortalSetting::TYPE_JSON,
            'description' => 'Public-safe calendar color coding for common schedule states.',
            'is_public' => true,
            'default' => [
                'scheduled' => '#2563eb',
                'completed' => '#16a34a',
                'cancelled' => '#dc2626',
                'pending' => '#d97706',
                'unavailable' => '#6b7280',
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
            'school.branding' => [
                'value' => ['required', 'array:primary_color,secondary_color,accent_color,logo_url,favicon_url,support_email'],
                'value.primary_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'value.secondary_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'value.accent_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'value.logo_url' => ['nullable', 'url', 'max:255'],
                'value.favicon_url' => ['nullable', 'url', 'max:255'],
                'value.support_email' => ['nullable', 'email', 'max:255'],
            ],
            'lessons.defaults' => [
                'value' => ['required', 'array:duration_minutes,buffer_minutes,allow_back_to_back,default_delivery_mode'],
                'value.duration_minutes' => ['required', 'integer', 'min:15', 'max:240'],
                'value.buffer_minutes' => ['required', 'integer', 'min:0', 'max:120'],
                'value.allow_back_to_back' => ['required', 'boolean'],
                'value.default_delivery_mode' => ['required', 'string', Rule::in(['online', 'in_person', 'hybrid'])],
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
            'notifications.rules' => [
                'value' => ['required', 'array:quiet_hours_enabled,quiet_hours_start,quiet_hours_end,digest_enabled,digest_frequency,escalation_minutes'],
                'value.quiet_hours_enabled' => ['required', 'boolean'],
                'value.quiet_hours_start' => ['required', 'date_format:H:i'],
                'value.quiet_hours_end' => ['required', 'date_format:H:i'],
                'value.digest_enabled' => ['required', 'boolean'],
                'value.digest_frequency' => ['required', 'string', Rule::in(['daily', 'weekly'])],
                'value.escalation_minutes' => ['required', 'integer', 'min:0', 'max:43200'],
            ],
            'email.templates' => [
                'value' => ['required', 'array:sender_name,reply_to,templates'],
                'value.sender_name' => ['required', 'string', 'max:120'],
                'value.reply_to' => ['nullable', 'email', 'max:255'],
                'value.templates' => ['required', 'array:welcome,lesson_reminder,invoice,password_reset'],
                'value.templates.welcome' => ['nullable', 'string', 'max:120'],
                'value.templates.lesson_reminder' => ['nullable', 'string', 'max:120'],
                'value.templates.invoice' => ['nullable', 'string', 'max:120'],
                'value.templates.password_reset' => ['nullable', 'string', 'max:120'],
            ],
            'attendance.status_options' => [
                'value' => ['required', 'array:default_status,statuses'],
                'value.default_status' => ['required', 'string', 'max:40'],
                'value.statuses' => ['required', 'array', 'min:1', 'max:12'],
                'value.statuses.*.key' => ['required', 'string', 'alpha_dash:ascii', 'max:40'],
                'value.statuses.*.label' => ['required', 'string', 'max:80'],
                'value.statuses.*.counts_as_attended' => ['required', 'boolean'],
            ],
            'user_roles.defaults' => [
                'value' => ['required', 'array:student_role,teacher_role,staff_role,require_email_verification,auto_activate_invited_users'],
                'value.student_role' => ['required', 'string', Rule::in(['student'])],
                'value.teacher_role' => ['required', 'string', Rule::in(['teacher'])],
                'value.staff_role' => ['required', 'string', Rule::in(['staff'])],
                'value.require_email_verification' => ['required', 'boolean'],
                'value.auto_activate_invited_users' => ['required', 'boolean'],
            ],
            'courses.settings' => [
                'value' => ['required', 'array:default_session_count,allow_self_enrollment,require_staff_assignment,default_visibility,archive_completed_after_days'],
                'value.default_session_count' => ['required', 'integer', 'min:1', 'max:200'],
                'value.allow_self_enrollment' => ['required', 'boolean'],
                'value.require_staff_assignment' => ['required', 'boolean'],
                'value.default_visibility' => ['required', 'string', Rule::in(['draft', 'published'])],
                'value.archive_completed_after_days' => ['required', 'integer', 'min:0', 'max:3650'],
            ],
            'localization.options' => [
                'value' => ['required', 'array:default_locale,supported_locales,date_format,time_format,first_day_of_week'],
                'value.default_locale' => ['required', 'string', 'max:12'],
                'value.supported_locales' => ['required', 'array', 'min:1', 'max:10'],
                'value.supported_locales.*' => ['required', 'string', 'max:12'],
                'value.date_format' => ['required', 'string', Rule::in(['Y-m-d', 'm/d/Y', 'd/m/Y', 'M j, Y'])],
                'value.time_format' => ['required', 'string', Rule::in(['H:i', 'g:i A'])],
                'value.first_day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
            ],
            'calendar.color_coding' => [
                'value' => ['required', 'array:scheduled,completed,cancelled,pending,unavailable'],
                'value.scheduled' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'value.completed' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'value.cancelled' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'value.pending' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'value.unavailable' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
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
