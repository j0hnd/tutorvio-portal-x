<?php

namespace Tests\Feature;

use App\Models\CourseProgram;
use App\Models\CourseProgramStudentAssignment;
use App\Models\Scheduling\ClassSchedule;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TrialEnrollmentReportApiTest extends TestCase
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

    public function test_admin_can_view_trial_enrollment_conversion_summary_and_rows(): void
    {
        $convertedStudent = $this->student(['name' => 'Converted Student']);
        $this->assignCourse($convertedStudent);
        $this->trialClass($convertedStudent, '2026-05-10 09:00:00', ClassSchedule::STATUS_COMPLETED);
        Subscription::factory()->create([
            'user_id' => $convertedStudent->id,
            'starts_at' => '2026-05-12',
            'ends_at' => '2026-06-12',
        ]);

        $notConvertedStudent = $this->student(['name' => 'Not Converted Student']);
        $this->trialClass($notConvertedStudent, '2026-05-11 09:00:00', ClassSchedule::STATUS_COMPLETED);

        $preExistingStudent = $this->student(['name' => 'Pre Existing Student']);
        $this->trialClass($preExistingStudent, '2026-05-15 09:00:00', ClassSchedule::STATUS_COMPLETED);
        Subscription::factory()->create([
            'user_id' => $preExistingStudent->id,
            'starts_at' => '2026-05-01',
            'ends_at' => '2026-06-01',
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/v1/admin/reports/trial-enrollments')
            ->assertOk()
            ->assertJsonPath('data.summary.total_trial_students_count', 3)
            ->assertJsonPath('data.summary.total_trial_classes_count', 3)
            ->assertJsonPath('data.summary.converted_enrolled_count', 1)
            ->assertJsonPath('data.summary.not_converted_count', 2)
            ->assertJsonPath('data.summary.pending_follow_up_count', null)
            ->assertJsonPath('data.summary.trial_to_enrollment_conversion_rate', 33.33)
            ->assertJsonCount(3, 'data.rows');

        $rows = collect($response->json('data.rows'))->keyBy('student_id');

        $this->assertSame($convertedStudent->id, $rows[$convertedStudent->id]['student_id']);
        $this->assertNull($rows[$convertedStudent->id]['prospect_id']);
        $this->assertSame('Converted Student', $rows[$convertedStudent->id]['student_name']);
        $this->assertNull($rows[$convertedStudent->id]['prospect_name']);
        $this->assertSame('2026-05-10', $rows[$convertedStudent->id]['trial_lesson_date']);
        $this->assertSame($this->teacher->id, $rows[$convertedStudent->id]['trial_teacher']['id']);
        $this->assertSame($this->courseProgram->id, $rows[$convertedStudent->id]['course']['id']);
        $this->assertSame(ClassSchedule::STATUS_COMPLETED, $rows[$convertedStudent->id]['trial_status']);
        $this->assertSame('enrolled', $rows[$convertedStudent->id]['enrollment_status']);
        $this->assertSame('2026-05-12', $rows[$convertedStudent->id]['enrollment_date']);
        $this->assertNull($rows[$convertedStudent->id]['follow_up_status']);
        $this->assertSame('not_converted', $rows[$preExistingStudent->id]['enrollment_status']);
        $this->assertNull($rows[$preExistingStudent->id]['enrollment_date']);
    }

    public function test_trial_enrollment_report_filters_by_date_teacher_student_course_and_status(): void
    {
        $matchingStudent = $this->student(['name' => 'Matching Student']);
        $this->assignCourse($matchingStudent);
        $this->trialClass($matchingStudent, '2026-05-15 09:00:00', ClassSchedule::STATUS_COMPLETED);
        Subscription::factory()->create([
            'user_id' => $matchingStudent->id,
            'starts_at' => '2026-05-16',
            'ends_at' => '2026-06-16',
        ]);

        $otherStudent = $this->student(['name' => 'Other Student']);
        $this->trialClass($otherStudent, '2026-05-18 09:00:00', ClassSchedule::STATUS_SCHEDULED);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/admin/reports/trial-enrollments?date_from=2026-05-01&date_to=2026-05-31&teacher_id='.$this->teacher->id.'&student_id='.$matchingStudent->id.'&course_id='.$this->courseProgram->id.'&status=enrolled')
            ->assertOk()
            ->assertJsonPath('data.summary.total_trial_students_count', 1)
            ->assertJsonPath('data.summary.converted_enrolled_count', 1)
            ->assertJsonCount(1, 'data.rows')
            ->assertJsonPath('data.rows.0.student_id', $matchingStudent->id)
            ->assertJsonPath('data.rows.0.enrollment_status', 'enrolled')
            ->assertJsonPath('data.filters.date_from', '2026-05-01')
            ->assertJsonPath('data.filters.date_to', '2026-05-31')
            ->assertJsonPath('data.filters.teacher_id', $this->teacher->id)
            ->assertJsonPath('data.filters.student_id', $matchingStudent->id)
            ->assertJsonPath('data.filters.course_id', $this->courseProgram->id)
            ->assertJsonPath('data.filters.status', 'enrolled');
    }

    public function test_trial_enrollment_report_returns_safe_empty_response_when_trial_data_is_missing(): void
    {
        $student = $this->student();
        Subscription::factory()->create([
            'user_id' => $student->id,
            'starts_at' => '2026-05-01',
            'ends_at' => '2026-06-01',
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/admin/reports/trial-enrollments')
            ->assertOk()
            ->assertJsonPath('data.summary.total_trial_students_count', 0)
            ->assertJsonPath('data.summary.total_trial_classes_count', 0)
            ->assertJsonPath('data.summary.converted_enrolled_count', 0)
            ->assertJsonPath('data.summary.not_converted_count', 0)
            ->assertJsonPath('data.summary.pending_follow_up_count', null)
            ->assertJsonPath('data.summary.trial_to_enrollment_conversion_rate', null)
            ->assertJsonPath('data.rows', []);
    }

    public function test_trial_enrollment_report_permission_rules(): void
    {
        $this->getJson('/api/v1/admin/reports/trial-enrollments')->assertUnauthorized();

        $staff = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('staff');

        Sanctum::actingAs($staff);
        $this->getJson('/api/v1/admin/reports/trial-enrollments')->assertForbidden();

        $staff->givePermissionTo('school_reports.view');
        $this->getJson('/api/v1/admin/reports/trial-enrollments')->assertOk();

        $student = $this->student();
        Sanctum::actingAs($student);

        $this->getJson('/api/v1/admin/reports/trial-enrollments')->assertForbidden();
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

    private function assignCourse(User $student): void
    {
        $student->studentProfile()->create([
            'assigned_teacher_id' => $this->teacher->id,
            'course' => 'Legacy Course',
            'start_date' => '2026-05-01',
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

    private function trialClass(User $student, string $startsAt, string $status): ClassSchedule
    {
        return ClassSchedule::create([
            'student_id' => $student->id,
            'teacher_id' => $this->teacher->id,
            'title' => 'Trial Class',
            'status' => $status,
            'class_type' => ClassSchedule::CLASS_TYPE_TRIAL,
            'timezone' => 'UTC',
            'starts_at' => $startsAt,
            'ends_at' => Carbon::parse($startsAt)->addMinutes(30),
            'created_by' => $this->admin->id,
        ]);
    }
}
