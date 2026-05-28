<?php

namespace Tests\Feature;

use App\Models\CourseProgram;
use App\Models\CourseProgramStudentAssignment;
use App\Models\LessonRecord;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AttendanceReportApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    private User $student;

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

        $this->student = User::factory()->create([
            'name' => 'Ada Student',
            'status' => User::STATUS_ACTIVE,
        ]);
        $this->student->assignRole('student');

        $this->courseProgram = CourseProgram::factory()->create([
            'title' => 'Business English',
            'placement_level' => 'B1',
        ]);

        CourseProgramStudentAssignment::create([
            'course_program_id' => $this->courseProgram->id,
            'student_id' => $this->student->id,
            'assigned_by' => $this->admin->id,
            'assigned_at' => '2026-05-01 09:00:00',
            'status' => CourseProgramStudentAssignment::STATUS_ACTIVE,
            'start_date' => '2026-05-01',
        ]);
    }

    public function test_attendance_report_returns_summary_totals_and_rows(): void
    {
        $present = $this->createLessonRecord([
            'attendance_status' => LessonRecord::ATTENDANCE_PRESENT,
            'lesson_status' => LessonRecord::STATUS_COMPLETED,
            'lesson_notes' => 'Great progress.',
        ]);
        $this->createLessonRecord([
            'scheduled_date' => '2026-06-11',
            'attendance_status' => LessonRecord::ATTENDANCE_LATE,
            'lesson_status' => LessonRecord::STATUS_COMPLETED,
        ]);
        $this->createLessonRecord([
            'scheduled_date' => '2026-06-12',
            'attendance_status' => LessonRecord::ATTENDANCE_ABSENT,
            'lesson_status' => LessonRecord::STATUS_COMPLETED,
        ]);
        $this->createLessonRecord([
            'scheduled_date' => '2026-06-13',
            'lesson_status' => LessonRecord::STATUS_CANCELLED,
            'attendance_status' => null,
        ]);
        $this->createLessonRecord([
            'scheduled_date' => '2026-06-14',
            'lesson_status' => LessonRecord::STATUS_RESCHEDULED,
            'attendance_status' => null,
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/admin/reports/attendance?date_from=2026-06-01&date_to=2026-06-30')
            ->assertOk()
            ->assertJsonPath('data.summary.total_lessons', 5)
            ->assertJsonPath('data.summary.attended_count', 2)
            ->assertJsonPath('data.summary.absent_count', 1)
            ->assertJsonPath('data.summary.cancelled_rescheduled_count', 2)
            ->assertJsonPath('data.summary.attendance_rate', 66.67)
            ->assertJsonCount(5, 'data.rows')
            ->assertJsonPath('data.rows.0.lesson_record_id', $present->id)
            ->assertJsonPath('data.rows.0.student.name', 'Ada Student')
            ->assertJsonPath('data.rows.0.teacher.id', $this->teacher->id)
            ->assertJsonPath('data.rows.0.course.title', 'Business English')
            ->assertJsonPath('data.rows.0.lesson_date', '2026-06-10')
            ->assertJsonPath('data.rows.0.attendance_status', LessonRecord::ATTENDANCE_PRESENT)
            ->assertJsonPath('data.rows.0.attendance_category', 'attended')
            ->assertJsonPath('data.rows.0.reason_or_note', 'Great progress.');
    }

    public function test_attendance_report_filters_rows(): void
    {
        $matching = $this->createLessonRecord([
            'attendance_status' => LessonRecord::ATTENDANCE_ABSENT,
            'lesson_status' => LessonRecord::STATUS_COMPLETED,
        ]);

        $otherTeacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $otherTeacher->assignRole('teacher');
        $otherStudent = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $otherStudent->assignRole('student');
        $otherCourse = CourseProgram::factory()->create(['title' => 'IELTS Prep']);

        CourseProgramStudentAssignment::create([
            'course_program_id' => $otherCourse->id,
            'student_id' => $otherStudent->id,
            'assigned_by' => $this->admin->id,
            'assigned_at' => '2026-05-01 09:00:00',
            'status' => CourseProgramStudentAssignment::STATUS_ACTIVE,
            'start_date' => '2026-05-01',
        ]);

        $this->createLessonRecord([
            'student_id' => $otherStudent->id,
            'teacher_id' => $otherTeacher->id,
            'scheduled_date' => '2026-06-10',
            'attendance_status' => LessonRecord::ATTENDANCE_ABSENT,
        ]);
        $this->createLessonRecord([
            'scheduled_date' => '2026-07-01',
            'attendance_status' => LessonRecord::ATTENDANCE_ABSENT,
        ]);
        $this->createLessonRecord([
            'scheduled_date' => '2026-06-15',
            'attendance_status' => LessonRecord::ATTENDANCE_PRESENT,
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/admin/reports/attendance?date_from=2026-06-01&date_to=2026-06-30&teacher_id='.$this->teacher->id.'&student_id='.$this->student->id.'&course_id='.$this->courseProgram->id.'&status=absent')
            ->assertOk()
            ->assertJsonPath('data.summary.total_lessons', 1)
            ->assertJsonCount(1, 'data.rows')
            ->assertJsonPath('data.rows.0.lesson_record_id', $matching->id)
            ->assertJsonPath('data.filters.status', LessonRecord::ATTENDANCE_ABSENT);
    }

    public function test_missed_class_report_returns_missed_rows_and_filters(): void
    {
        $missed = $this->createLessonRecord([
            'attendance_status' => LessonRecord::ATTENDANCE_NO_SHOW,
            'lesson_status' => LessonRecord::STATUS_COMPLETED,
            'internal_remarks' => 'Student did not join.',
        ]);
        $this->createLessonRecord([
            'scheduled_date' => '2026-06-11',
            'attendance_status' => LessonRecord::ATTENDANCE_PRESENT,
        ]);
        $this->createLessonRecord([
            'scheduled_date' => '2026-06-12',
            'attendance_status' => null,
            'lesson_status' => LessonRecord::STATUS_MISSED_BY_TEACHER,
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/admin/reports/missed-classes?date_from=2026-06-01&date_to=2026-06-30&teacher_id='.$this->teacher->id.'&student_id='.$this->student->id.'&course_id='.$this->courseProgram->id.'&status=no_show')
            ->assertOk()
            ->assertJsonPath('data.summary.missed_class_count', 1)
            ->assertJsonCount(1, 'data.rows')
            ->assertJsonPath('data.rows.0.lesson_record_id', $missed->id)
            ->assertJsonPath('data.rows.0.student.name', 'Ada Student')
            ->assertJsonPath('data.rows.0.teacher.name', $this->teacher->name)
            ->assertJsonPath('data.rows.0.course.id', $this->courseProgram->id)
            ->assertJsonPath('data.rows.0.lesson_date', '2026-06-10')
            ->assertJsonPath('data.rows.0.attendance_status', LessonRecord::ATTENDANCE_NO_SHOW)
            ->assertJsonPath('data.rows.0.missed_status', LessonRecord::ATTENDANCE_NO_SHOW)
            ->assertJsonPath('data.rows.0.reason_or_note', 'Student did not join.');
    }

    public function test_report_routes_require_role_and_permission(): void
    {
        $this->getJson('/api/v1/admin/reports/attendance')->assertUnauthorized();
        $this->getJson('/api/v1/admin/reports/missed-classes')->assertUnauthorized();

        $staff = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('staff');

        Sanctum::actingAs($staff);
        $this->getJson('/api/v1/admin/reports/attendance')->assertForbidden();
        $this->getJson('/api/v1/admin/reports/missed-classes')->assertForbidden();

        $staff->givePermissionTo('school_reports.view');
        $this->getJson('/api/v1/admin/reports/attendance')->assertOk();
        $this->getJson('/api/v1/admin/reports/missed-classes')->assertOk();

        Sanctum::actingAs($this->student);
        $this->getJson('/api/v1/admin/reports/attendance')->assertForbidden();
        $this->getJson('/api/v1/admin/reports/missed-classes')->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createLessonRecord(array $overrides = []): LessonRecord
    {
        return LessonRecord::create([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'scheduled_date' => '2026-06-10',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'lesson_type' => LessonRecord::TYPE_BUSINESS_ENGLISH,
            'lesson_status' => LessonRecord::STATUS_COMPLETED,
            'attendance_status' => LessonRecord::ATTENDANCE_PRESENT,
            'lesson_notes' => null,
            'internal_remarks' => null,
            ...$overrides,
        ]);
    }
}
