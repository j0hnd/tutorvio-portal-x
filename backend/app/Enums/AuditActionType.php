<?php

namespace App\Enums;

enum AuditActionType: string
{
    case AUTH_LOGIN = 'auth.login';
    case AUTH_LOGOUT = 'auth.logout';
    case LESSON_CREATED = 'lesson.created';
    case LESSON_UPDATED = 'lesson.updated';
    case SCHEDULE_UPDATED = 'schedule.updated';
    case STUDENT_UPDATED = 'student.updated';
    case ATTENDANCE_MARKED = 'attendance.marked';
    case PAYMENT_CREATED = 'payment.created';
    case PAYMENT_UPDATED = 'payment.updated';
    case PAYMENT_ADJUSTED = 'payment.adjusted';
    case PACKAGE_ASSIGNED = 'package.assigned';
    case PACKAGE_UPDATED = 'package.updated';
    case PACKAGE_STATUS_CHANGED = 'package.status_changed';
    case LESSON_BALANCE_ADJUSTED = 'lesson_balance.adjusted';
    case PERMISSION_UPDATED = 'permission.updated';
    case ROLE_UPDATED = 'role.updated';
    case STAFF_ACCESS_LEVEL_CHANGED = 'staff_access_level.changed';
    case PORTAL_SETTING_UPDATED = 'portal_setting.updated';
    case FILE_UPLOADED = 'file.uploaded';
    case FILE_DOWNLOADED = 'file.downloaded';
    case ISSUE_STATUS_CHANGED = 'issue.status_changed';
    case ISSUE_RESOLUTION_UPDATED = 'issue.resolution_updated';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
