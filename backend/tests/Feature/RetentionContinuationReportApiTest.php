<?php

namespace Tests\Feature;

use App\Models\CourseProgram;
use App\Models\CourseProgramStudentAssignment;
use App\Models\LessonRecord;
use App\Models\Subscription;
use App\Models\TeacherStudentAssignment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RetentionContinuationReportApiTest extends TestCase
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

        Carbon::setTestNow('2026-05-26 10:00:00');

        $this->admin = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->admin->assignRole('admin');

        $this->teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->teacher->assignRole('teacher');

        $this->courseProgram = CourseProgram::factory()->create([
            'title' => 'Business English',
            'placement_level' => 'B1',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_can_view_retention_continuation_summary_and_rows(): void
    {
        $renewedStudent = $this->student(['name' => 'Renewed Student']);
        $this->assignTeacherAndCourse($renewedStudent);
        $previousSubscription = Subscription::factory()->expired()->create([
            'user_id' => $renewedStudent->id,
            'starts_at' => '2026-04-01',
            'ends_at' => '2026-05-01',
        ]);
        Subscription::factory()->create([
            'user_id' => $renewedStudent->id,
            'plan_name' => 'Renewed Plan',
            'renewed_from_subscription_id' => $previousSubscription->id,
            'starts_at' => '2026-05-02',
            'ends_at' => '2026-06-30',
        ]);
        $this->completedLesson($renewedStudent, '2026-05-20');

        $nearingEndStudent = $this->student(['name' => 'Nearing End Student']);
        Subscription::factory()->create([
            'user_id' => $nearingEndStudent->id,
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => '2026-05-01',
            'ends_at' => '2026-05-30',
        ]);

        $endedStudent = $this->student(['name' => 'Ended Student']);
        Subscription::factory()->expired()->create([
            'user_id' => $endedStudent->id,
            'starts_at' => '2026-04-01',
            'ends_at' => '2026-05-10',
        ]);

        $inactiveStudent = $this->student([
            'name' => 'Inactive Student',
            'status' => User::STATUS_INACTIVE,
        ]);
        Subscription::factory()->create([
            'user_id' => $inactiveStudent->id,
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => '2026-05-01',
            'ends_at' => '2026-07-01',
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/v1/admin/reports/retention-continuation')
            ->assertOk()
            ->assertJsonPath('data.summary.active_students_count', 3)
            ->assertJsonPath('data.summary.continued_renewed_students_count', 1)
            ->assertJsonPath('data.summary.students_nearing_package_end_count', 1)
            ->assertJsonPath('data.summary.students_with_ended_packages_count', 1)
            ->assertJsonPath('data.summary.inactive_dropped_students_count', 1)
            ->assertJsonPath('data.summary.retention_continuation_rate', 50)
            ->assertJsonCount(4, 'data.rows');

        $rows = collect($response->json('data.rows'))->keyBy('student_id');

        $this->assertSame(Subscription::STATUS_EXPIRED, $rows[$endedStudent->public_id]['current_package_status']);
        $this->assertNull($rows[$endedStudent->public_id]['renewal_continuation_status']);
        $this->assertSame($this->courseProgram->public_id, $rows[$renewedStudent->public_id]['course']['id']);
        $this->assertSame($this->teacher->public_id, $rows[$renewedStudent->public_id]['assigned_teacher']['id']);
        $this->assertSame(Subscription::STATUS_ACTIVE, $rows[$renewedStudent->public_id]['current_package_status']);
        $this->assertSame('2026-06-30', $rows[$renewedStudent->public_id]['package_end_date']);
        $this->assertSame('2026-05-20', $rows[$renewedStudent->public_id]['last_lesson_date']);
        $this->assertSame('renewed', $rows[$renewedStudent->public_id]['renewal_continuation_status']);
        $this->assertSame(User::STATUS_ACTIVE, $rows[$renewedStudent->public_id]['student_status']);
    }

    public function test_retention_continuation_report_filters_by_package_end_date_teacher_student_course_and_status(): void
    {
        $matchingStudent = $this->student(['name' => 'Matching Student']);
        $this->assignTeacherAndCourse($matchingStudent);
        Subscription::factory()->create([
            'user_id' => $matchingStudent->id,
            'starts_at' => '2026-05-01',
            'ends_at' => '2026-05-20',
        ]);

        $otherStudent = $this->student(['name' => 'Other Student']);
        $otherCourse = CourseProgram::factory()->create(['title' => 'IELTS Prep']);
        CourseProgramStudentAssignment::create([
            'course_program_id' => $otherCourse->id,
            'student_id' => $otherStudent->id,
            'assigned_by' => $this->admin->id,
            'assigned_at' => '2026-05-01 09:00:00',
            'status' => CourseProgramStudentAssignment::STATUS_ACTIVE,
            'start_date' => '2026-05-01',
        ]);
        Subscription::factory()->create([
            'user_id' => $otherStudent->id,
            'starts_at' => '2026-05-01',
            'ends_at' => '2026-06-20',
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/admin/reports/retention-continuation?date_from=2026-05-01&date_to=2026-05-31&teacher_id='.$this->teacher->id.'&student_id='.$matchingStudent->id.'&course_id='.$this->courseProgram->id.'&status=active')
            ->assertOk()
            ->assertJsonPath('data.summary.active_students_count', 1)
            ->assertJsonCount(1, 'data.rows')
            ->assertJsonPath('data.rows.0.student_id', $matchingStudent->public_id)
            ->assertJsonPath('data.filters.date_from', '2026-05-01')
            ->assertJsonPath('data.filters.date_to', '2026-05-31')
            ->assertJsonPath('data.filters.teacher_id', $this->teacher->id)
            ->assertJsonPath('data.filters.student_id', $matchingStudent->id)
            ->assertJsonPath('data.filters.course_id', $this->courseProgram->id)
            ->assertJsonPath('data.filters.status', User::STATUS_ACTIVE);
    }

    public function test_retention_continuation_report_permission_rules(): void
    {
        $this->getJson('/api/v1/admin/reports/retention-continuation')->assertUnauthorized();

        $staff = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('staff');

        Sanctum::actingAs($staff);
        $this->getJson('/api/v1/admin/reports/retention-continuation')->assertForbidden();

        $staff->givePermissionTo('school_reports.view');
        $this->getJson('/api/v1/admin/reports/retention-continuation')->assertOk();

        $student = $this->student();
        Sanctum::actingAs($student);

        $this->getJson('/api/v1/admin/reports/retention-continuation')->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function student(array $attributes = []): User
    {
        $student = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            ...$attributes,
        ]);
        $student->assignRole('student');

        return $student;
    }

    private function assignTeacherAndCourse(User $student): void
    {
        $student->studentProfile()->create([
            'assigned_teacher_id' => $this->teacher->id,
            'course' => 'Legacy Course',
            'start_date' => '2026-05-01',
        ]);

        TeacherStudentAssignment::create([
            'student_id' => $student->id,
            'teacher_id' => $this->teacher->id,
            'assigned_by' => $this->admin->id,
            'assigned_at' => '2026-05-01 08:00:00',
            'status' => TeacherStudentAssignment::STATUS_ACTIVE,
            'active_student_id' => $student->id,
        ]);

        CourseProgramStudentAssignment::create([
            'course_program_id' => $this->courseProgram->id,
            'student_id' => $student->id,
            'assigned_by' => $this->admin->id,
            'assigned_at' => '2026-05-01 09:00:00',
            'status' => CourseProgramStudentAssignment::STATUS_ACTIVE,
            'start_date' => '2026-05-01',
        ]);
    }

    private function completedLesson(User $student, string $date): void
    {
        LessonRecord::create([
            'student_id' => $student->id,
            'teacher_id' => $this->teacher->id,
            'scheduled_date' => $date,
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
            'lesson_type' => LessonRecord::TYPE_BUSINESS_ENGLISH,
            'lesson_status' => LessonRecord::STATUS_COMPLETED,
            'attendance_status' => LessonRecord::ATTENDANCE_PRESENT,
            'is_completed' => true,
            'completed_at' => $date.' 10:30:00',
        ]);
    }
}
