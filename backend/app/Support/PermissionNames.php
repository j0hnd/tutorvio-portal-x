<?php

namespace App\Support;

final class PermissionNames
{
    public const OPERATIONAL_ANNOUNCEMENTS_VIEW = 'operational_announcements.view';

    public const OPERATIONAL_ANNOUNCEMENTS_MANAGE = 'operational_announcements.manage';

    public const PORTAL_SETTINGS_VIEW = 'portal_settings.view';

    public const PORTAL_SETTINGS_MANAGE = 'portal_settings.manage';

    public const FORM_TEMPLATES_VIEW = 'form_templates.view';

    public const FORM_TEMPLATES_MANAGE = 'form_templates.manage';

    public const ACADEMIC_RECORDS_VIEW = 'academic_records.view';

    public const ACADEMIC_RECORDS_MANAGE = 'academic_records.manage';

    public const ISSUE_REPORTS_VIEW = 'issue_reports.view';

    public const ISSUE_REPORTS_CREATE = 'issue_reports.create';

    public const ISSUE_REPORTS_MANAGE = 'issue_reports.manage';

    public const ISSUE_REPORTS_ASSIGN = 'issue_reports.assign';

    public const ISSUE_REPORTS_RESOLVE = 'issue_reports.resolve';

    public const SCHEDULE_CHANGE_REQUESTS_VIEW = 'schedule_change_requests.view';

    public const SCHEDULE_CHANGE_REQUESTS_CREATE = 'schedule_change_requests.create';

    public const SCHEDULE_CHANGE_REQUESTS_MANAGE = 'schedule_change_requests.manage';

    /**
     * @return array<int, string>
     */
    public static function operationalControl(): array
    {
        return [
            self::OPERATIONAL_ANNOUNCEMENTS_VIEW,
            self::OPERATIONAL_ANNOUNCEMENTS_MANAGE,
            self::PORTAL_SETTINGS_VIEW,
            self::PORTAL_SETTINGS_MANAGE,
            self::FORM_TEMPLATES_VIEW,
            self::FORM_TEMPLATES_MANAGE,
            self::ACADEMIC_RECORDS_VIEW,
            self::ACADEMIC_RECORDS_MANAGE,
            self::ISSUE_REPORTS_VIEW,
            self::ISSUE_REPORTS_CREATE,
            self::ISSUE_REPORTS_MANAGE,
            self::ISSUE_REPORTS_ASSIGN,
            self::ISSUE_REPORTS_RESOLVE,
            self::SCHEDULE_CHANGE_REQUESTS_VIEW,
            self::SCHEDULE_CHANGE_REQUESTS_CREATE,
            self::SCHEDULE_CHANGE_REQUESTS_MANAGE,
        ];
    }
}
