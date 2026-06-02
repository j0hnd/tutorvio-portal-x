<?php

namespace Tests\Feature;

use App\Models\CourseProgram;
use App\Models\CourseProgramStudentAssignment;
use App\Models\LessonRecord;
use App\Models\Scheduling\ClassSchedule;
use App\Models\Scheduling\TeacherAvailability;
use App\Models\TeacherStudentAssignment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TeacherLoadReportApiTest extends TestCase
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

        $this->admin = User::factory()->create(['status' => User::STATUS_ACTIVE, 'timezone' => 'Asia/Manila']);
        $this->admin->assignRole('admin');

        $this->teacher = User::factory()->create(['status' => User::STATUS_ACTIVE, 'timezone' => 'Asia/Manila']);
        $this->teacher->assignRole('teacher');
        $this->teacher->teacherProfile()->create([
            'class_load' => 3,
            'internal_status' => 'available',
        ]);

        $this->student = User::factory()->create(['status' => User::STATUS_ACTIVE, 'timezone' => 'Asia/Manila']);
        $this->student->assignRole('student');

        $this->courseProgram = CourseProgram::factory()->create(['title' => 'Business English']);
    }

    public function test_admin_can_get_teacher_load_summary_counts(): void
    {
        $this->seedLoadFixture();

        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/v1/reports/teacher-load?date_from=2026-06-01&date_to=2026-06-01&timezone=Asia/Manila&course_id='.$this->courseProgram->id);

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data.summary')
            ->assertJsonPath('data.summary.0.teacher_id', $this->teacher->id)
            ->assertJsonPath('data.summary.0.teacher_name', $this->teacher->name)
            ->assertJsonPath('data.summary.0.active_assigned_students_count', 1)
            ->assertJsonPath('data.summary.0.scheduled_lessons_count', 1)
            ->assertJsonPath('data.summary.0.completed_lessons_count', 1)
            ->assertJsonPath('data.summary.0.missed_cancelled_rescheduled_lessons_count', 2)
            ->assertJsonPath('data.summary.0.capacity_value', 3)
            ->assertJsonPath('data.summary.0.available_open_slots', 1)
            ->assertJsonPath('data.summary.0.workload_status', 'available')
            ->assertJsonPath('data.filters.date_from', '2026-06-01')
            ->assertJsonPath('data.filters.course_id', $this->courseProgram->id);

        $this->assertResponseDoesNotExposeTeacherPrivateData($response->json());
    }

    public function test_teacher_filter_limits_report_to_one_teacher(): void
    {
        $this->seedLoadFixture();

        $otherTeacher = User::factory()->create(['status' => User::STATUS_ACTIVE, 'timezone' => 'Asia/Manila']);
        $otherTeacher->assignRole('teacher');
        $otherTeacher->teacherProfile()->create(['class_load' => 10]);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/reports/teacher-load?date_from=2026-06-01&date_to=2026-06-01&teacher_id='.$this->teacher->id)
            ->assertOk()
            ->assertJsonCount(1, 'data.summary')
            ->assertJsonPath('data.summary.0.teacher_id', $this->teacher->id);
    }

    public function test_report_role_access_is_enforced(): void
    {
        $this->getJson('/api/v1/reports/teacher-load')->assertUnauthorized();

        $staff = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('staff');

        Sanctum::actingAs($staff);
        $this->getJson('/api/v1/reports/teacher-load')->assertForbidden();

        $staff->givePermissionTo('teacher_workloads.view');
        $this->getJson('/api/v1/reports/teacher-load')->assertOk();

        $otherTeacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $otherTeacher->assignRole('teacher');

        Sanctum::actingAs($this->teacher);
        $this->getJson('/api/v1/reports/teacher-load?teacher_id='.$otherTeacher->id)
            ->assertOk()
            ->assertJsonCount(0, 'data.summary');

        Sanctum::actingAs($this->student);
        $this->getJson('/api/v1/reports/teacher-load')->assertForbidden();
    }

    private function seedLoadFixture(): void
    {
        $courseStudent = $this->student;
        $otherStudent = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $otherStudent->assignRole('student');
        $otherCourse = CourseProgram::factory()->create(['title' => 'IELTS Prep']);

        foreach ([[$courseStudent, $this->courseProgram], [$otherStudent, $otherCourse]] as [$student, $courseProgram]) {
            TeacherStudentAssignment::create([
                'student_id' => $student->id,
                'teacher_id' => $this->teacher->id,
                'assigned_by' => $this->admin->id,
                'assigned_at' => '2026-05-01 00:00:00',
                'status' => TeacherStudentAssignment::STATUS_ACTIVE,
                'active_student_id' => $student->id,
            ]);

            CourseProgramStudentAssignment::create([
                'course_program_id' => $courseProgram->id,
                'student_id' => $student->id,
                'assigned_by' => $this->admin->id,
                'assigned_at' => '2026-05-01 00:00:00',
                'status' => CourseProgramStudentAssignment::STATUS_ACTIVE,
                'start_date' => '2026-05-01',
            ]);
        }

        TeacherAvailability::create([
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'timezone' => 'Asia/Manila',
        ]);

        ClassSchedule::create([
            'student_id' => $courseStudent->id,
            'teacher_id' => $this->teacher->id,
            'status' => ClassSchedule::STATUS_SCHEDULED,
            'class_type' => ClassSchedule::CLASS_TYPE_REGULAR,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01 02:00:00',
            'ends_at' => '2026-06-01 03:00:00',
        ]);

        ClassSchedule::create([
            'student_id' => $otherStudent->id,
            'teacher_id' => $this->teacher->id,
            'status' => ClassSchedule::STATUS_SCHEDULED,
            'class_type' => ClassSchedule::CLASS_TYPE_REGULAR,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01 03:00:00',
            'ends_at' => '2026-06-01 04:00:00',
        ]);

        $this->createLessonRecord($courseStudent, [
            'lesson_status' => LessonRecord::STATUS_COMPLETED,
            'attendance_status' => LessonRecord::ATTENDANCE_PRESENT,
            'is_completed' => true,
        ]);
        $this->createLessonRecord($courseStudent, [
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'lesson_status' => LessonRecord::STATUS_CANCELLED,
            'attendance_status' => null,
        ]);
        $this->createLessonRecord($courseStudent, [
            'start_time' => '11:00:00',
            'end_time' => '12:00:00',
            'lesson_status' => LessonRecord::STATUS_COMPLETED,
            'attendance_status' => LessonRecord::ATTENDANCE_NO_SHOW,
        ]);
        $this->createLessonRecord($otherStudent, [
            'lesson_status' => LessonRecord::STATUS_COMPLETED,
            'attendance_status' => LessonRecord::ATTENDANCE_PRESENT,
            'is_completed' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createLessonRecord(User $student, array $overrides = []): LessonRecord
    {
        return LessonRecord::create([
            'student_id' => $student->id,
            'teacher_id' => $this->teacher->id,
            'scheduled_date' => '2026-06-01',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'lesson_type' => LessonRecord::TYPE_BUSINESS_ENGLISH,
            'lesson_status' => LessonRecord::STATUS_SCHEDULED,
            'attendance_status' => null,
            ...$overrides,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertResponseDoesNotExposeTeacherPrivateData(array $payload): void
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);

        foreach (['email', 'phone', 'internal_status', 'teaching_notes', 'internal_remarks', 'pay_rate', 'payroll'] as $sensitiveKey) {
            $this->assertStringNotContainsString($sensitiveKey, $json);
        }
    }
}
