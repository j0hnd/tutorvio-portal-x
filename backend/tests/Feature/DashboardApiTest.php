<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Homework;
use App\Models\IssueReport;
use App\Models\Lesson;
use App\Models\Material;
use App\Models\Subscription;
use App\Models\User;
use App\Services\DashboardCacheService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
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
        Cache::flush();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
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

        $upcomingLesson = Lesson::create([
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
            'total_lesson_count' => 20,
            'consumed_lesson_count' => 2,
            'remaining_lesson_count' => 18,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonth(),
        ]);

        $homework = Homework::create([
            'lesson_id' => $upcomingLesson->id,
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'title' => 'Dashboard Homework',
            'status' => Homework::STATUS_ASSIGNED,
            'due_date' => now()->addDays(3),
        ]);

        $otherHomework = Homework::create([
            'lesson_id' => Lesson::create([
                'student_id' => $otherStudent->id,
                'teacher_id' => $teacher->id,
                'start_time' => now()->addDays(4),
                'end_time' => now()->addDays(4)->addHour(),
                'status' => 'scheduled',
            ])->id,
            'student_id' => $otherStudent->id,
            'teacher_id' => $teacher->id,
            'title' => 'Other Student Homework',
            'status' => Homework::STATUS_ASSIGNED,
            'due_date' => now()->addDays(3),
        ]);

        $announcement = Announcement::create([
            'title' => 'Student-visible notice',
            'body' => 'Bring your workbook.',
            'status' => Announcement::STATUS_PUBLISHED,
            'type' => Announcement::TYPE_ADMIN_ANNOUNCEMENT,
            'author_id' => $teacher->id,
            'published_at' => now()->subHour(),
        ]);
        $announcement->recipients()->create([
            'user_id' => $student->id,
            'matched_targets' => ['role:student'],
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
            ->assertJsonPath('data.summary.announcements.0.id', $announcement->public_id)
            ->assertJsonPath('data.summary.announcements.0.title', 'Student-visible notice')
            ->assertJsonPath('data.summary.homework.0.id', $homework->public_id)
            ->assertJsonPath('data.summary.homework.0.title', 'Dashboard Homework')
            ->assertJsonPath('data.summary.lesson_balance.remaining_lessons', 18)
            ->assertJsonPath('data.summary.next_lesson.teacher.id', $teacher->public_id)
            ->assertJsonPath('data.summary.next_lesson.join_url', null)
            ->assertJsonCount(1, 'data.summary.upcoming_lessons')
            ->assertJsonCount(1, 'data.summary.recent_materials')
            ->assertJsonPath('data.summary.recent_materials.0.title', 'Placement Prep')
            ->assertJsonMissingPath('data.summary.users')
            ->assertJsonMissingPath('data.summary.students');

        $payload = $this->getJson('/api/v1/dashboard')->json();
        $this->assertDashboardUsesPublicIds($payload['data']);
        $this->assertStringNotContainsString('Internal lesson preparation note.', json_encode($payload));
        $this->assertStringNotContainsString('Billing issue hidden from student.', json_encode($payload));
        $this->assertStringNotContainsString('Other Student Material', json_encode($payload));
        $this->assertStringNotContainsString($otherHomework->public_id, json_encode($payload));
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

    public function test_student_dashboard_only_exposes_join_url_for_joinable_lesson(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01 08:50:00'));

        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole('student');

        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $teacher->assignRole('teacher');

        $student->studentProfile()->create([
            'assigned_teacher_id' => $teacher->id,
        ]);

        Lesson::create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'start_time' => Carbon::parse('2026-06-01 09:00:00'),
            'end_time' => Carbon::parse('2026-06-01 10:00:00'),
            'status' => 'scheduled',
            'meeting_link' => 'https://meet.example.com/dashboard-lesson',
            'meeting_provider' => Lesson::PROVIDER_GOOGLE_MEET,
            'meeting_metadata' => [
                'google_event_id' => 'dashboard-event-1',
            ],
            'join_available_from' => Carbon::parse('2026-06-01 08:45:00'),
            'join_available_until' => Carbon::parse('2026-06-01 10:15:00'),
        ]);

        Sanctum::actingAs($student);

        $response = $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.summary.upcoming_lessons.0.meeting_provider', Lesson::PROVIDER_GOOGLE_MEET)
            ->assertJsonPath('data.summary.upcoming_lessons.0.is_join_available', true)
            ->assertJsonPath('data.summary.next_lesson.join_url', 'https://meet.example.com/dashboard-lesson');

        $this->assertStringNotContainsString('google_event_id', json_encode($response->json('data.summary.upcoming_lessons')));
        $this->assertStringNotContainsString('https://meet.example.com/dashboard-lesson', json_encode($response->json('data.summary.upcoming_lessons')));
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

        $completedLesson = Lesson::create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'start_time' => now()->subDay(),
            'end_time' => now()->subDay()->addHour(),
            'status' => 'completed',
        ]);

        $homework = Homework::create([
            'lesson_id' => $completedLesson->id,
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'title' => 'Submitted Writing Task',
            'status' => Homework::STATUS_COMPLETED,
            'completed_at' => now()->subHour(),
        ]);

        $announcement = Announcement::create([
            'title' => 'Teacher-visible announcement',
            'body' => 'Please review class notes.',
            'status' => Announcement::STATUS_PUBLISHED,
            'type' => Announcement::TYPE_ADMIN_ANNOUNCEMENT,
            'author_id' => $otherTeacher->id,
            'published_at' => now()->subHour(),
        ]);
        $announcement->recipients()->create([
            'user_id' => $teacher->id,
            'matched_targets' => ['role:teacher'],
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
            ->assertJsonPath('data.summary.todays_schedule.0.student.id', $student->public_id)
            ->assertJsonPath('data.summary.upcoming_classes.0.student.id', $student->public_id)
            ->assertJsonPath('data.summary.students_needing_notes_or_follow_up.0.student.id', $student->public_id)
            ->assertJsonPath('data.summary.assigned_student_profiles.0.student.id', $student->public_id)
            ->assertJsonPath('data.summary.recent_lesson_submissions.0.id', $homework->public_id)
            ->assertJsonPath('data.summary.admin_announcements.0.id', $announcement->public_id)
            ->assertJsonPath('data.summary.unsupported_sections.lesson_documentation_records', 'No dedicated lesson documentation model exists; completed lessons are returned as documentation shortcuts instead.')
            ->assertJsonMissingPath('data.summary.users')
            ->assertJsonMissingPath('data.summary.materials');

        $payload = $this->getJson('/api/v1/dashboard')->json();
        $this->assertDashboardUsesPublicIds($payload['data']);
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

        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole('student');

        $issue = IssueReport::create([
            'issue_type' => IssueReport::TYPE_TECHNICAL_ISSUE,
            'status' => IssueReport::STATUS_OPEN,
            'priority' => IssueReport::PRIORITY_HIGH,
            'reporter_id' => $student->id,
            'assigned_to_id' => $staff->id,
            'title' => 'Staff assigned issue',
            'description' => 'Private issue details.',
        ]);

        $announcement = Announcement::create([
            'title' => 'Staff operational notice',
            'body' => 'Review today operations queue.',
            'status' => Announcement::STATUS_PUBLISHED,
            'type' => Announcement::TYPE_ADMIN_ANNOUNCEMENT,
            'author_id' => $staff->id,
            'published_at' => now()->subHour(),
        ]);
        $announcement->recipients()->create([
            'user_id' => $staff->id,
            'matched_targets' => ['role:staff'],
        ]);

        Sanctum::actingAs($staff);

        $response = $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.role', 'staff')
            ->assertJsonPath('data.summary.assigned_tasks.0.id', $issue->public_id)
            ->assertJsonPath('data.summary.assigned_tasks.0.title', 'Staff assigned issue')
            ->assertJsonMissingPath('data.summary.assigned_tasks.0.description')
            ->assertJsonPath('data.summary.operational_notices.0.id', $announcement->public_id)
            ->assertJsonPath('data.summary.operational_notices.0.title', 'Staff operational notice')
            ->assertJsonPath('data.sections', ['assigned_tasks', 'operational_notices'])
            ->assertJsonMissingPath('data.summary.users')
            ->assertJsonMissingPath('data.summary.students')
            ->assertJsonMissingPath('data.summary.classes')
            ->assertJsonMissingPath('data.summary.dashboard_widgets');

        $this->assertDashboardUsesPublicIds($response->json('data'));
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
            'remaining_lesson_count' => 2,
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

        $announcement = Announcement::create([
            'title' => 'Admin operations notice',
            'body' => 'A published notice.',
            'status' => Announcement::STATUS_PUBLISHED,
            'type' => Announcement::TYPE_ADMIN_ANNOUNCEMENT,
            'author_id' => $admin->id,
            'published_at' => now()->subHour(),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/dashboard')
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
            ->assertJsonPath('data.summary.payment_package_alerts.low_lesson_balance', 1)
            ->assertJsonPath('data.summary.operational_announcements.0.id', $announcement->public_id)
            ->assertJsonPath('data.summary.operational_announcements.0.title', 'Admin operations notice')
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

        $this->assertDashboardUsesPublicIds($response->json('data'));
    }

    public function test_student_dashboard_cache_is_scoped_to_the_authenticated_student(): void
    {
        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole('student');

        $otherStudent = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $otherStudent->assignRole('student');

        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $teacher->assignRole('teacher');

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
            ->assertJsonPath('data.summary.classes.total', 1);

        Sanctum::actingAs($otherStudent);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.summary.classes.total', 0)
            ->assertJsonPath('data.summary.next_lesson', null);
    }

    public function test_staff_dashboard_cache_is_scoped_to_current_permissions(): void
    {
        $staff = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('staff');

        User::factory()->create(['status' => User::STATUS_ACTIVE])->assignRole('student');

        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.summary', []);

        $staff->givePermissionTo('users.view');

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.summary.users.total', 2)
            ->assertJsonPath('data.summary.dashboard_widgets.0.key', 'users');
    }

    public function test_dashboard_summary_cache_refreshes_when_lessons_change(): void
    {
        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole('student');

        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $teacher->assignRole('teacher');

        Sanctum::actingAs($student);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.summary.classes.total', 0);

        Lesson::create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'status' => 'scheduled',
        ]);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.summary.classes.total', 1)
            ->assertJsonPath('data.summary.learning_progress.scheduled_lessons', 1);
    }

    public function test_dashboard_summary_cache_version_refreshes_when_source_models_change(): void
    {
        Cache::forever(DashboardCacheService::VERSION_KEY, 1);

        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole('student');

        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $teacher->assignRole('teacher');

        $lesson = Lesson::create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'status' => 'scheduled',
        ]);

        $this->assertSame(2, Cache::get(DashboardCacheService::VERSION_KEY));

        Homework::create([
            'lesson_id' => $lesson->id,
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'title' => 'Dashboard cache homework',
            'status' => Homework::STATUS_ASSIGNED,
        ]);

        $this->assertSame(3, Cache::get(DashboardCacheService::VERSION_KEY));

        Announcement::create([
            'title' => 'Dashboard cache announcement',
            'body' => 'Announcement body.',
            'status' => Announcement::STATUS_DRAFT,
            'type' => Announcement::TYPE_ADMIN_ANNOUNCEMENT,
            'author_id' => $teacher->id,
        ]);

        $this->assertSame(4, Cache::get(DashboardCacheService::VERSION_KEY));

        IssueReport::create([
            'issue_type' => IssueReport::TYPE_TECHNICAL_ISSUE,
            'status' => IssueReport::STATUS_OPEN,
            'priority' => IssueReport::PRIORITY_NORMAL,
            'reporter_id' => $student->id,
            'title' => 'Dashboard cache issue',
            'description' => 'Issue details.',
        ]);

        $this->assertSame(5, Cache::get(DashboardCacheService::VERSION_KEY));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertDashboardUsesPublicIds(array $payload, string $path = 'data'): void
    {
        foreach ($payload as $key => $value) {
            $currentPath = "{$path}.{$key}";

            if (is_array($value)) {
                $this->assertDashboardUsesPublicIds($value, $currentPath);

                continue;
            }

            if ($key === 'id' || str_ends_with((string) $key, '_id')) {
                if ($value === null) {
                    continue;
                }

                $this->assertIsString($value, "{$currentPath} should be a public ID string.");
                $this->assertFalse(is_numeric($value), "{$currentPath} should not expose a numeric database ID.");
            }
        }
    }
}
