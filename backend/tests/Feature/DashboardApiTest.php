<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Material;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->getJson('/api/v1/dashboard')->assertUnauthorized();
    }

    public function test_student_dashboard_only_returns_own_summary(): void
    {
        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole('student');

        $otherStudent = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $otherStudent->assignRole('student');

        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $teacher->assignRole('teacher');

        $student->studentProfile()->create([
            'assigned_teacher_id' => $teacher->id,
            'course' => 'General English',
            'english_level' => 'A2',
            'current_level' => 'A2.2',
            'class_type' => '1:1',
            'teacher_notes' => 'Practice short answers this week.',
            'internal_notes' => 'Billing issue hidden from student.',
        ]);

        Lesson::create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'status' => 'scheduled',
            'notes' => 'Internal lesson preparation note.',
        ]);

        Lesson::create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'start_time' => now()->subDay(),
            'end_time' => now()->subDay()->addHour(),
            'status' => 'completed',
        ]);

        Lesson::create([
            'student_id' => $otherStudent->id,
            'teacher_id' => $teacher->id,
            'start_time' => now()->addDays(2),
            'end_time' => now()->addDays(2)->addHour(),
            'status' => 'scheduled',
        ]);

        $material = Material::create(['title' => 'Placement Prep']);
        $material->students()->attach($student->id, [
            'assigned_at' => now(),
            'completed_at' => now(),
        ]);

        $otherMaterial = Material::create(['title' => 'Other Student Material']);
        $otherMaterial->students()->attach($otherStudent->id, [
            'assigned_at' => now(),
        ]);

        Subscription::create([
            'user_id' => $student->id,
            'plan_name' => 'Starter',
            'status' => 'active',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonth(),
        ]);

        Sanctum::actingAs($student);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.role', 'student')
            ->assertJsonPath('data.summary.classes.total', 2)
            ->assertJsonPath('data.summary.materials.assigned', 1)
            ->assertJsonPath('data.summary.materials.completed', 1)
            ->assertJsonPath('data.summary.subscription.plan_name', 'Starter')
            ->assertJsonPath('data.summary.active_plan.plan_name', 'Starter')
            ->assertJsonPath('data.summary.latest_teacher_note', 'Practice short answers this week.')
            ->assertJsonPath('data.summary.learning_progress.completed_lessons', 1)
            ->assertJsonPath('data.summary.learning_progress.scheduled_lessons', 1)
            ->assertJsonPath('data.summary.assigned_course.course', 'General English')
            ->assertJsonPath('data.summary.assigned_course.current_level', 'A2.2')
            ->assertJsonPath('data.summary.reminders', [])
            ->assertJsonPath('data.summary.announcements', [])
            ->assertJsonPath('data.summary.homework', [])
            ->assertJsonPath('data.summary.lesson_balance', null)
            ->assertJsonPath('data.summary.next_lesson.teacher.id', $teacher->id)
            ->assertJsonPath('data.summary.next_lesson.join_url', null)
            ->assertJsonCount(1, 'data.summary.upcoming_lessons')
            ->assertJsonCount(1, 'data.summary.recent_materials')
            ->assertJsonPath('data.summary.recent_materials.0.title', 'Placement Prep')
            ->assertJsonMissingPath('data.summary.users')
            ->assertJsonMissingPath('data.summary.students');

        $payload = $this->getJson('/api/v1/dashboard')->json();
        $this->assertStringNotContainsString('Internal lesson preparation note.', json_encode($payload));
        $this->assertStringNotContainsString('Billing issue hidden from student.', json_encode($payload));
        $this->assertStringNotContainsString('Other Student Material', json_encode($payload));
    }

    public function test_student_dashboard_does_not_expose_teacher_or_admin_sections(): void
    {
        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole('student');

        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $teacher->assignRole('teacher');

        $student->studentProfile()->create([
            'assigned_teacher_id' => $teacher->id,
            'course' => 'Student Course',
            'teacher_notes' => 'Student-visible note.',
        ]);

        Lesson::create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'status' => 'scheduled',
        ]);

        Sanctum::actingAs($student);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.role', 'student')
            ->assertJsonPath('data.sections', ['classes', 'materials', 'subscription'])
            ->assertJsonMissingPath('data.summary.operations')
            ->assertJsonMissingPath('data.summary.users')
            ->assertJsonMissingPath('data.summary.students')
            ->assertJsonMissingPath('data.summary.teachers')
            ->assertJsonMissingPath('data.summary.enrollments')
            ->assertJsonMissingPath('data.summary.payment_package_alerts')
            ->assertJsonMissingPath('data.summary.quick_links')
            ->assertJsonMissingPath('data.summary.todays_schedule')
            ->assertJsonMissingPath('data.summary.upcoming_classes')
            ->assertJsonMissingPath('data.summary.assigned_student_profiles')
            ->assertJsonMissingPath('data.summary.lesson_documentation_shortcuts');
    }

    public function test_teacher_dashboard_only_returns_teacher_scoped_summary(): void
    {
        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $teacher->assignRole('teacher');

        $otherTeacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $otherTeacher->assignRole('teacher');

        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole('student');
        $student->studentProfile()->create([
            'assigned_teacher_id' => $teacher->id,
            'course' => 'IELTS Prep',
            'english_level' => 'B1',
            'current_level' => 'B1.2',
            'teacher_notes' => null,
            'internal_notes' => 'Admin-only billing context.',
        ]);

        $otherStudent = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $otherStudent->assignRole('student');
        $otherStudent->studentProfile()->create([
            'assigned_teacher_id' => $otherTeacher->id,
            'course' => 'Other Course',
            'teacher_notes' => 'Other teacher note.',
            'internal_notes' => 'Other hidden context.',
        ]);

        Lesson::create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'start_time' => now()->setTime(10, 0),
            'end_time' => now()->setTime(11, 0),
            'status' => 'scheduled',
            'notes' => 'Today teacher preparation note.',
        ]);

        Lesson::create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'start_time' => now()->addDay()->setTime(10, 0),
            'end_time' => now()->addDay()->setTime(11, 0),
            'status' => 'scheduled',
        ]);

        Lesson::create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'start_time' => now()->subDay(),
            'end_time' => now()->subDay()->addHour(),
            'status' => 'completed',
        ]);

        Lesson::create([
            'student_id' => $otherStudent->id,
            'teacher_id' => $teacher->id,
            'start_time' => now()->addDay()->setTime(14, 0),
            'end_time' => now()->addDay()->setTime(15, 0),
            'status' => 'scheduled',
            'notes' => 'Should not be visible because student is not assigned.',
        ]);

        Lesson::create([
            'student_id' => $otherStudent->id,
            'teacher_id' => $otherTeacher->id,
            'start_time' => now()->subDay(),
            'end_time' => now()->subDay()->addHour(),
            'status' => 'completed',
        ]);

        Sanctum::actingAs($teacher);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.role', 'teacher')
            ->assertJsonPath('data.summary.students.assigned', 1)
            ->assertJsonPath('data.summary.classes.total', 3)
            ->assertJsonCount(1, 'data.summary.todays_schedule')
            ->assertJsonCount(1, 'data.summary.upcoming_classes')
            ->assertJsonCount(1, 'data.summary.students_needing_notes_or_follow_up')
            ->assertJsonCount(1, 'data.summary.assigned_student_profiles')
            ->assertJsonCount(1, 'data.summary.lesson_documentation_shortcuts')
            ->assertJsonPath('data.summary.todays_schedule.0.student.id', $student->id)
            ->assertJsonPath('data.summary.upcoming_classes.0.student.id', $student->id)
            ->assertJsonPath('data.summary.students_needing_notes_or_follow_up.0.student.id', $student->id)
            ->assertJsonPath('data.summary.assigned_student_profiles.0.student.id', $student->id)
            ->assertJsonPath('data.summary.recent_lesson_submissions', [])
            ->assertJsonPath('data.summary.admin_announcements', [])
            ->assertJsonMissingPath('data.summary.users')
            ->assertJsonMissingPath('data.summary.materials');

        $payload = $this->getJson('/api/v1/dashboard')->json();
        $encoded = json_encode($payload);

        $this->assertStringContainsString('IELTS Prep', $encoded);
        $this->assertStringNotContainsString('Other Course', $encoded);
        $this->assertStringNotContainsString('Other teacher note.', $encoded);
        $this->assertStringNotContainsString('Other hidden context.', $encoded);
        $this->assertStringNotContainsString('Admin-only billing context.', $encoded);
        $this->assertStringNotContainsString('Should not be visible because student is not assigned.', $encoded);
    }

    public function test_teacher_dashboard_does_not_expose_admin_summary_data(): void
    {
        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $teacher->assignRole('teacher');

        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole('student');
        $student->studentProfile()->create([
            'assigned_teacher_id' => $teacher->id,
            'teacher_notes' => null,
        ]);

        $unassignedStudent = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $unassignedStudent->assignRole('student');
        $unassignedStudent->studentProfile()->create([
            'teacher_notes' => null,
        ]);

        Subscription::create([
            'user_id' => $unassignedStudent->id,
            'plan_name' => 'Admin Only Billing Context',
            'status' => 'expired',
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($teacher);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.role', 'teacher')
            ->assertJsonPath('data.sections', ['students', 'classes'])
            ->assertJsonMissingPath('data.summary.operations')
            ->assertJsonMissingPath('data.summary.users')
            ->assertJsonMissingPath('data.summary.teachers')
            ->assertJsonMissingPath('data.summary.enrollments')
            ->assertJsonMissingPath('data.summary.payment_package_alerts')
            ->assertJsonMissingPath('data.summary.operational_announcements')
            ->assertJsonMissingPath('data.summary.quick_links');

        $payload = $this->getJson('/api/v1/dashboard')->json();
        $this->assertStringNotContainsString('Admin Only Billing Context', json_encode($payload));
    }

    public function test_staff_dashboard_only_includes_permitted_sections(): void
    {
        $staff = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('staff');
        $staff->givePermissionTo('students.view');

        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole('student');

        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.role', 'staff')
            ->assertJsonPath('data.summary.students.total', 1)
            ->assertJsonPath('data.summary.dashboard_widgets.0.key', 'students')
            ->assertJsonPath('data.sections', ['students'])
            ->assertJsonMissingPath('data.summary.users')
            ->assertJsonMissingPath('data.summary.classes')
            ->assertJsonMissingPath('data.summary.assigned_tasks')
            ->assertJsonMissingPath('data.summary.operational_notices');
    }

    public function test_staff_dashboard_with_no_permissions_has_no_admin_data(): void
    {
        $staff = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('staff');

        User::factory()->create(['status' => User::STATUS_ACTIVE])->assignRole('student');

        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.role', 'staff')
            ->assertJsonPath('data.summary', [])
            ->assertJsonPath('data.sections', [])
            ->assertJsonMissingPath('data.summary.operations')
            ->assertJsonMissingPath('data.summary.users')
            ->assertJsonMissingPath('data.summary.students')
            ->assertJsonMissingPath('data.summary.classes')
            ->assertJsonMissingPath('data.summary.dashboard_widgets');
    }

    public function test_staff_dashboard_includes_permission_allowed_empty_sections(): void
    {
        $staff = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('staff');
        $staff->givePermissionTo([
            'dashboard.tasks.view',
            'dashboard.operational_notices.view',
        ]);

        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.role', 'staff')
            ->assertJsonPath('data.summary.assigned_tasks', [])
            ->assertJsonPath('data.summary.operational_notices', [])
            ->assertJsonPath('data.sections', ['assigned_tasks', 'operational_notices'])
            ->assertJsonMissingPath('data.summary.users')
            ->assertJsonMissingPath('data.summary.students')
            ->assertJsonMissingPath('data.summary.classes')
            ->assertJsonMissingPath('data.summary.dashboard_widgets');
    }

    public function test_staff_dashboard_excludes_sections_without_matching_permissions(): void
    {
        $staff = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('staff');
        $staff->givePermissionTo('users.view');

        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole('student');

        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $teacher->assignRole('teacher');

        Lesson::create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'status' => 'scheduled',
        ]);

        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.role', 'staff')
            ->assertJsonPath('data.summary.users.total', 3)
            ->assertJsonPath('data.summary.dashboard_widgets.0.key', 'users')
            ->assertJsonPath('data.sections', ['users'])
            ->assertJsonMissingPath('data.summary.students')
            ->assertJsonMissingPath('data.summary.classes')
            ->assertJsonMissingPath('data.summary.assigned_tasks')
            ->assertJsonMissingPath('data.summary.operational_notices')
            ->assertJsonMissingPath('data.summary.operations')
            ->assertJsonMissingPath('data.summary.enrollments')
            ->assertJsonMissingPath('data.summary.payment_package_alerts')
            ->assertJsonMissingPath('data.summary.quick_links');
    }

    public function test_admin_dashboard_returns_admin_summary(): void
    {
        $admin = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $admin->assignRole('admin');

        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole('student');
        $student->studentProfile()->create([
            'teacher_notes' => null,
        ]);

        $assignedStudent = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $assignedStudent->assignRole('student');

        $invitedStudent = User::factory()->create(['status' => User::STATUS_INVITED]);
        $invitedStudent->assignRole('student');

        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $teacher->assignRole('teacher');

        $assignedStudent->studentProfile()->create([
            'assigned_teacher_id' => $teacher->id,
            'teacher_notes' => 'Placement complete.',
        ]);

        $todaysLesson = Lesson::create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'start_time' => now()->setTime(9, 0),
            'end_time' => now()->setTime(10, 0),
            'status' => 'scheduled',
        ]);

        $todaysLesson->attendances()->create([
            'student_id' => $student->id,
            'status' => 'absent',
        ]);

        Lesson::create([
            'student_id' => $assignedStudent->id,
            'teacher_id' => $teacher->id,
            'start_time' => now()->addDay()->setTime(11, 0),
            'end_time' => now()->addDay()->setTime(12, 0),
            'status' => 'no_show',
        ]);

        Subscription::create([
            'user_id' => $student->id,
            'plan_name' => 'Starter',
            'status' => 'active',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addDays(3),
        ]);

        Subscription::create([
            'user_id' => $assignedStudent->id,
            'plan_name' => 'Expired',
            'status' => 'expired',
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.summary.operations.active_students', 2)
            ->assertJsonPath('data.summary.operations.active_teachers', 1)
            ->assertJsonPath('data.summary.operations.todays_classes', 1)
            ->assertJsonPath('data.summary.operations.missed_classes', 2)
            ->assertJsonPath('data.summary.operations.pending_teacher_notes', 1)
            ->assertJsonPath('data.summary.users.total', 5)
            ->assertJsonPath('data.summary.students.total', 3)
            ->assertJsonPath('data.summary.students.active', 2)
            ->assertJsonPath('data.summary.teachers.active', 1)
            ->assertJsonPath('data.summary.enrollments.total_students', 3)
            ->assertJsonPath('data.summary.enrollments.active_students', 2)
            ->assertJsonPath('data.summary.enrollments.invited_students', 1)
            ->assertJsonPath('data.summary.enrollments.assigned_students', 1)
            ->assertJsonPath('data.summary.enrollments.unassigned_students', 2)
            ->assertJsonPath('data.summary.payment_package_alerts.expired_subscriptions', 1)
            ->assertJsonPath('data.summary.payment_package_alerts.expiring_within_7_days', 1)
            ->assertJsonPath('data.summary.payment_package_alerts.inactive_subscriptions', 1)
            ->assertJsonPath('data.summary.operational_announcements', [])
            ->assertJsonPath('data.summary.quick_links', [
                'user_management',
                'student_management',
                'teacher_management',
                'class_management',
                'enrollments',
                'payments',
                'packages',
                'announcements',
            ])
            ->assertJsonPath('data.sections', ['users', 'students', 'classes']);
    }
}
