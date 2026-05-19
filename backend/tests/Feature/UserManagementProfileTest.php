<?php

namespace Tests\Feature;

use App\Models\StaffProfile;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Models\UserStatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_management_profiles_and_status_history_relationships_are_available(): void
    {
        $admin = User::factory()->create();
        $teacher = User::factory()->create([
            'created_by' => $admin->id,
            'phone' => '+15551234567',
            'timezone' => 'America/New_York',
        ]);
        $student = User::factory()->create();
        $staff = User::factory()->create();

        $teacherProfile = TeacherProfile::create([
            'user_id' => $teacher->id,
            'specialization' => 'Business English',
            'teaching_availability' => ['monday' => ['09:00-12:00']],
            'internal_status' => 'available',
        ]);

        $studentProfile = StudentProfile::create([
            'user_id' => $student->id,
            'english_level' => 'B1',
            'course' => 'General English',
            'assigned_teacher_id' => $teacher->id,
            'class_type' => 'one_on_one',
            'preferences' => 'Morning classes',
            'goals' => 'Improve speaking confidence',
        ]);

        $staffProfile = StaffProfile::create([
            'user_id' => $staff->id,
            'department' => 'Operations',
            'access_limitations' => 'Scheduling only',
        ]);

        $statusHistory = UserStatusHistory::create([
            'user_id' => $student->id,
            'old_status' => User::STATUS_INVITED,
            'new_status' => User::STATUS_ACTIVE,
            'reason' => 'Invitation accepted',
            'changed_by' => $admin->id,
        ]);

        $this->assertTrue($teacher->teacherProfile->is($teacherProfile));
        $this->assertTrue($student->studentProfile->is($studentProfile));
        $this->assertTrue($staff->staffProfile->is($staffProfile));
        $this->assertTrue($student->studentProfile->assignedTeacher->is($teacher));
        $this->assertTrue($teacher->assignedStudents->first()->is($studentProfile));
        $this->assertTrue($student->statusHistories->first()->is($statusHistory));
        $this->assertTrue($statusHistory->changedBy->is($admin));
        $this->assertTrue($teacher->createdBy->is($admin));
        $this->assertSame(['monday' => ['09:00-12:00']], $teacher->teacherProfile->teaching_availability);
    }
}
