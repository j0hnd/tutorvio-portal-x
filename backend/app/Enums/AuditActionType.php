<?php

namespace App\Enums;

enum AuditActionType: string
{
    case LESSON_CREATED = 'lesson.created';
    case SCHEDULE_UPDATED = 'schedule.updated';
    case STUDENT_UPDATED = 'student.updated';
    case ATTENDANCE_MARKED = 'attendance.marked';
    case PAYMENT_UPDATED = 'payment.updated';
    case PACKAGE_UPDATED = 'package.updated';
    case PERMISSION_UPDATED = 'permission.updated';
    case ROLE_UPDATED = 'role.updated';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
