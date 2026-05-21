<?php

namespace Database\Seeders;

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
            'schedules.view', 'schedules.create', 'schedules.update', 'schedules.delete',
            'availability.view', 'availability.manage',
            'holidays.view', 'holidays.manage',
            'reminders.view', 'reminders.manage',
            'dashboard.tasks.view', 'dashboard.operational_notices.view',
            'admin.access',
        ];

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
            'reminders.view',
        ]);

        $teacherRole->syncPermissions([
            'students.view',
            'students.update',
            'classes.view',
            'classes.update',
            'lesson_records.view',
            'lesson_records.update',
            'schedules.view',
            'schedules.update',
            'availability.view',
            'availability.manage',
            'reminders.view',
        ]);

        $staffRole->syncPermissions([]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
