<?php

namespace Tests\Feature;

use App\Models\CourseProgram;
use App\Models\CourseProgramStudentAssignment;
use App\Models\Subscription;
use App\Models\TeacherStudentAssignment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ActiveStudentsReportApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    private CourseProgram $courseProgram;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->admin->assignRole('admin');

        $this->teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->teacher->assignRole('teacher');

        $this->courseProgram = CourseProgram::factory()->create([
            'title' => 'Business English',
            'placement_level' => 'B1',
        ]);
    }

    public function test_admin_can_view_active_students_report(): void
    {
        $student = $this->createActiveStudent([
            'name' => 'Ada Student',
            'created_at' => '2026-05-01 09:00:00',
        ]);

        $student->studentProfile()->create([
            'assigned_teacher_id' => $this->teacher->id,
            'course' => 'Legacy Course',
            'start_date' => '2026-05-10',
        ]);

        TeacherStudentAssignment::create([
            'student_id' => $student->id,
            'teacher_id' => $this->teacher->id,
            'assigned_by' => $this->admin->id,
            'assigned_at' => '2026-05-09 08:00:00',
            'status' => TeacherStudentAssignment::STATUS_ACTIVE,
            'active_student_id' => $student->id,
        ]);

        CourseProgramStudentAssignment::create([
            'course_program_id' => $this->courseProgram->id,
            'student_id' => $student->id,
            'assigned_by' => $this->admin->id,
            'assigned_at' => '2026-05-09 09:00:00',
            'status' => CourseProgramStudentAssignment::STATUS_ACTIVE,
            'start_date' => '2026-05-10',
        ]);

        Subscription::factory()->create([
            'user_id' => $student->id,
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => '2026-05-10 00:00:00',
        ]);

        $inactiveStudent = $this->createInactiveStudent(['name' => 'Inactive Student']);
        Subscription::factory()->create([
            'user_id' => $inactiveStudent->id,
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/admin/reports/active-students')
            ->assertOk()
            ->assertJsonPath('data.summary.active_students_count', 1)
            ->assertJsonCount(1, 'data.rows')
            ->assertJsonPath('data.rows.0.student_id', $student->public_id)
            ->assertJsonPath('data.rows.0.student_name', 'Ada Student')
            ->assertJsonPath('data.rows.0.course.id', $this->courseProgram->public_id)
            ->assertJsonPath('data.rows.0.course.title', 'Business English')
            ->assertJsonPath('data.rows.0.course.placement_level', 'B1')
            ->assertJsonPath('data.rows.0.assigned_teacher.id', $this->teacher->public_id)
            ->assertJsonPath('data.rows.0.assigned_teacher.name', $this->teacher->name)
            ->assertJsonPath('data.rows.0.enrollment_status', CourseProgramStudentAssignment::STATUS_ACTIVE)
            ->assertJsonPath('data.rows.0.package_status', Subscription::STATUS_ACTIVE)
            ->assertJsonPath('data.rows.0.created_enrolled_date', '2026-05-10')
            ->assertJsonPath('data.rows.0.current_status', User::STATUS_ACTIVE);
    }

    public function test_active_students_report_filters_rows(): void
    {
        $matchingStudent = $this->createActiveStudent(['name' => 'Matching Student']);
        $matchingStudent->studentProfile()->create([
            'assigned_teacher_id' => $this->teacher->id,
            'start_date' => '2026-06-15',
        ]);
        CourseProgramStudentAssignment::create([
            'course_program_id' => $this->courseProgram->id,
            'student_id' => $matchingStudent->id,
            'assigned_by' => $this->admin->id,
            'assigned_at' => '2026-06-15 09:00:00',
            'status' => CourseProgramStudentAssignment::STATUS_ACTIVE,
            'start_date' => '2026-06-15',
        ]);

        $otherTeacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $otherTeacher->assignRole('teacher');
        $otherCourse = CourseProgram::factory()->create(['title' => 'IELTS Prep']);

        $otherStudent = $this->createActiveStudent(['name' => 'Other Student']);
        $otherStudent->studentProfile()->create([
            'assigned_teacher_id' => $otherTeacher->id,
            'start_date' => '2026-07-01',
        ]);
        CourseProgramStudentAssignment::create([
            'course_program_id' => $otherCourse->id,
            'student_id' => $otherStudent->id,
            'assigned_by' => $this->admin->id,
            'assigned_at' => '2026-07-01 09:00:00',
            'status' => CourseProgramStudentAssignment::STATUS_ACTIVE,
            'start_date' => '2026-07-01',
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/admin/reports/active-students?date_from=2026-06-01&date_to=2026-06-30&teacher_id='.$this->teacher->id.'&student_id='.$matchingStudent->id.'&course_id='.$this->courseProgram->id.'&status=active')
            ->assertOk()
            ->assertJsonPath('data.summary.active_students_count', 1)
            ->assertJsonCount(1, 'data.rows')
            ->assertJsonPath('data.rows.0.student_id', $matchingStudent->public_id)
            ->assertJsonPath('data.filters.date_from', '2026-06-01')
            ->assertJsonPath('data.filters.date_to', '2026-06-30')
            ->assertJsonPath('data.filters.teacher_id', $this->teacher->id)
            ->assertJsonPath('data.filters.student_id', $matchingStudent->id)
            ->assertJsonPath('data.filters.course_id', $this->courseProgram->id)
            ->assertJsonPath('data.filters.status', User::STATUS_ACTIVE);

        $this->getJson('/api/v1/admin/reports/active-students?status=inactive')
            ->assertOk()
            ->assertJsonPath('data.summary.active_students_count', 0)
            ->assertJsonCount(0, 'data.rows');
    }

    public function test_active_students_report_paginates_rows_without_changing_summary(): void
    {
        $first = $this->createActiveStudent(['name' => 'Ada Student']);
        $second = $this->createActiveStudent(['name' => 'Bert Student']);

        foreach ([$first, $second] as $student) {
            CourseProgramStudentAssignment::create([
                'course_program_id' => $this->courseProgram->id,
                'student_id' => $student->id,
                'assigned_by' => $this->admin->id,
                'assigned_at' => '2026-05-09 09:00:00',
                'status' => CourseProgramStudentAssignment::STATUS_ACTIVE,
                'start_date' => '2026-05-10',
            ]);
        }

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/admin/reports/active-students?per_page=1&page=2')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('summary.active_students_count', 2)
            ->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.student_id', $second->public_id)
            ->assertJsonPath('pagination.current_page', 2)
            ->assertJsonPath('pagination.per_page', 1)
            ->assertJsonPath('pagination.total', 2)
            ->assertJsonPath('pagination.last_page', 2)
            ->assertJsonPath('pagination.from', 2)
            ->assertJsonPath('pagination.to', 2)
            ->assertJsonPath('pagination.has_more_pages', false);
    }

    public function test_active_students_report_forbids_unauthorized_users(): void
    {
        $this->getJson('/api/v1/admin/reports/active-students')->assertUnauthorized();

        $staff = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('staff');

        Sanctum::actingAs($staff);
        $this->getJson('/api/v1/admin/reports/active-students')->assertForbidden();

        $staff->givePermissionTo('school_reports.view');
        $this->getJson('/api/v1/admin/reports/active-students')->assertOk();

        $student = $this->createActiveStudent();

        Sanctum::actingAs($student);
        $this->getJson('/api/v1/admin/reports/active-students')->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createActiveStudent(array $overrides = []): User
    {
        $student = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            ...$overrides,
        ]);
        $student->assignRole('student');

        return $student;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createInactiveStudent(array $overrides = []): User
    {
        $student = User::factory()->create([
            'status' => User::STATUS_INACTIVE,
            ...$overrides,
        ]);
        $student->assignRole('student');

        return $student;
    }
}
