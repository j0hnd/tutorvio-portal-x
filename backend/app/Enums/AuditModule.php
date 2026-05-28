<?php

namespace App\Enums;

enum AuditModule: string
{
    case LESSONS = 'lessons';
    case SCHEDULING = 'scheduling';
    case STUDENTS = 'students';
    case ATTENDANCE = 'attendance';
    case BILLING = 'billing';
    case PACKAGES = 'packages';
    case PERMISSIONS = 'permissions';
    case USERS = 'users';
    case PORTAL_SETTINGS = 'portal_settings';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
