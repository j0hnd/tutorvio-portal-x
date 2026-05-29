<?php

namespace App\Enums;

enum AuditModule: string
{
    case AUTH = 'auth';
    case LESSONS = 'lessons';
    case SCHEDULING = 'scheduling';
    case STUDENTS = 'students';
    case ATTENDANCE = 'attendance';
    case BILLING = 'billing';
    case PACKAGES = 'packages';
    case PERMISSIONS = 'permissions';
    case USERS = 'users';
    case PORTAL_SETTINGS = 'portal_settings';
    case LEARNING_RESOURCES = 'learning_resources';
    case ISSUE_REPORTS = 'issue_reports';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
