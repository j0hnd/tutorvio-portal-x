<?php

namespace Database\Seeders;

use App\Services\PortalMetadataService;
use App\Support\PermissionNames;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'users.view', 'users.create', 'users.update', 'users.delete',
            'users.activate', 'users.deactivate', 'users.assign_roles',
            'students.view', 'students.create', 'students.update', 'students.delete',
            'classes.view', 'classes.create', 'classes.update', 'classes.delete',
            'lesson_records.view', 'lesson_records.create', 'lesson_records.update', 'lesson_records.delete',
            'student_progress_records.view', 'student_progress_records.create', 'student_progress_records.update', 'student_progress_records.delete',
            'academic_records.view', 'academic_records.manage',
            'school_reports.view',
            'lesson_notes.view', 'lesson_notes.create', 'lesson_notes.update',
            'homeworks.view',
            'learning_resources.view', 'learning_resources.create', 'learning_resources.update', 'learning_resources.delete',
            'course_programs.view', 'course_programs.create', 'course_programs.update', 'course_programs.delete',
            'schedules.view', 'schedules.create', 'schedules.update', 'schedules.delete',
            'availability.view', 'availability.manage',
            'holidays.view', 'holidays.manage',
            'reminders.view', 'reminders.manage',
            'messages.view', 'messages.manage',
            'chat_escalations.view', 'chat_escalations.manage',
            'message_templates.view', 'message_templates.manage',
            'invoices.view', 'invoices.create', 'invoices.update',
            'teacher_assignments.view', 'teacher_assignments.manage',
            'teacher_change_requests.view', 'teacher_change_requests.manage',
            'teacher_workloads.view',
            'teacher_compensations.view', 'teacher_compensations.manage',
            'teacher_earnings.view', 'teacher_earnings.view_own',
            'payout_periods.view', 'payout_periods.manage',
            'payroll.view', 'payroll.manage',
            'subscriptions.view', 'subscriptions.create', 'subscriptions.update', 'subscriptions.delete',
            'announcements.manage', 'notifications.history.view',
            'audit_logs.view',
            'dashboard.tasks.view', 'dashboard.operational_notices.view',
            'admin.access',
        ];
        $permissions = array_values(array_unique(array_merge(
            $permissions,
            PermissionNames::operationalControl(),
        )));

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $studentRole = Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $teacherRole = Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $staffRole = Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $adminRole->syncPermissions($permissions);

        $studentRole->syncPermissions([
            'classes.view',
            'schedules.view',
            'schedule_change_requests.create',
            'reminders.view',
            'messages.view',
        ]);

        $teacherRole->syncPermissions([
            'students.view',
            'students.update',
            'classes.view',
            'classes.update',
            'lesson_records.view',
            'lesson_records.update',
            'student_progress_records.view',
            'student_progress_records.create',
            'student_progress_records.update',
            'academic_records.view',
            'lesson_notes.view',
            'lesson_notes.create',
            'lesson_notes.update',
            'schedules.view',
            'schedules.update',
            'schedule_change_requests.create',
            'availability.view',
            'availability.manage',
            'reminders.view',
            'messages.view',
        ]);

        // Staff is permissionless by default. Assign named permissions per
        // staff account or team once an operational access level is known.
        $staffRole->syncPermissions([]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        app(PortalMetadataService::class)->forgetRolePermissionMetadata();
    }
}
