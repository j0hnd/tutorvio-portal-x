<?php

namespace Tests\Feature;

use App\Models\CourseProgram;
use App\Models\LessonRecord;
use App\Models\TeacherCompensation;
use App\Models\TeacherEarning;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TeacherEarningApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    private User $otherTeacher;

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

        $this->otherTeacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->otherTeacher->assignRole('teacher');

        $this->courseProgram = CourseProgram::factory()->create();
    }

    public function test_admin_can_list_teacher_earnings_with_required_response_fields_and_filters(): void
    {
        $earning = $this->createEarning($this->teacher, [
            'scheduled_date' => '2026-06-10',
            'lesson_type' => LessonRecord::TYPE_BUSINESS_ENGLISH,
            'pay_model' => TeacherCompensation::PAY_MODEL_PER_HOUR,
            'status' => TeacherEarning::STATUS_APPROVED,
            'payout_period' => '2026-06',
            'course_program_id' => $this->courseProgram->id,
            'internal_admin_notes' => 'Teacher should not see this through earnings.',
        ]);

        $this->createEarning($this->otherTeacher, [
            'scheduled_date' => '2026-07-10',
            'lesson_type' => LessonRecord::TYPE_EXAM_PREPARATION,
            'pay_model' => TeacherCompensation::PAY_MODEL_PER_LESSON,
            'status' => TeacherEarning::STATUS_PENDING,
            'payout_period' => '2026-07',
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/admin/teacher-earnings?teacher_id='.$this->teacher->id.'&payout_period=2026-06&date_from=2026-06-01&date_to=2026-06-30&status=approved&lesson_type=business_english&course='.$this->courseProgram->id.'&pay_model=per_hour')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonMissingPath('data.0.id')
            ->assertJsonPath('data.0.teacher_name', $this->teacher->name)
            ->assertJsonPath('data.0.earning_source.type', TeacherEarning::SOURCE_LESSON_RECORD)
            ->assertJsonPath('data.0.earning_source.id', $earning->lessonRecord->public_id)
            ->assertJsonPath('data.0.lesson_reference.id', $earning->lessonRecord->public_id)
            ->assertJsonPath('data.0.course_reference.id', $this->courseProgram->public_id)
            ->assertJsonPath('data.0.pay_model', TeacherCompensation::PAY_MODEL_PER_HOUR)
            ->assertJsonPath('data.0.rate_used', '40.00')
            ->assertJsonPath('data.0.quantity', '1.50')
            ->assertJsonPath('data.0.amount', '60.00')
            ->assertJsonPath('data.0.currency', 'USD')
            ->assertJsonPath('data.0.status', TeacherEarning::STATUS_APPROVED)
            ->assertJsonPath('data.0.earning_date', '2026-06-10')
            ->assertJsonPath('data.0.payout_period', '2026-06')
            ->assertJsonMissing(['internal_admin_notes' => 'Teacher should not see this through earnings.']);
    }

    public function test_admin_can_view_earnings_for_specific_teacher(): void
    {
        $earning = $this->createEarning($this->teacher);
        $this->createEarning($this->otherTeacher);

        Sanctum::actingAs($this->admin);

        $this->getJson("/api/v1/admin/teachers/{$this->teacher->public_id}/teacher-earnings")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonMissingPath('data.0.id')
            ->assertJsonPath('data.0.teacher_id', $this->teacher->public_id);
    }

    public function test_staff_can_view_earnings_only_with_payroll_permission(): void
    {
        $this->createEarning($this->teacher);

        $staff = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('staff');

        Sanctum::actingAs($staff);
        $this->getJson('/api/v1/admin/teacher-earnings')->assertForbidden();

        $staff->givePermissionTo('teacher_earnings.view');

        $this->getJson('/api/v1/admin/teacher-earnings')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_students_and_default_teachers_cannot_access_earnings(): void
    {
        $this->createEarning($this->teacher, [
            'internal_admin_notes' => 'Private payroll note.',
        ]);

        Sanctum::actingAs($this->teacher);
        $this->getJson('/api/v1/teacher-earnings')
            ->assertForbidden()
            ->assertJsonMissing(['internal_admin_notes' => 'Private payroll note.']);

        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole('student');

        Sanctum::actingAs($student);
        $this->getJson('/api/v1/teacher-earnings')->assertForbidden();
        $this->getJson('/api/v1/admin/teacher-earnings')->assertForbidden();
    }

    public function test_teacher_can_view_own_earnings_when_self_access_is_enabled(): void
    {
        $ownEarning = $this->createEarning($this->teacher);
        $this->createEarning($this->otherTeacher);

        config(['teacher_earnings.teacher_self_access_enabled' => true]);

        Sanctum::actingAs($this->teacher);

        $this->getJson('/api/v1/teacher-earnings')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonMissingPath('data.0.id')
            ->assertJsonPath('data.0.teacher_id', $this->teacher->public_id);
    }

    public function test_teacher_with_explicit_permission_can_view_only_own_earnings(): void
    {
        $ownEarning = $this->createEarning($this->teacher);
        $this->createEarning($this->otherTeacher);

        $this->teacher->givePermissionTo('teacher_earnings.view_own');

        Sanctum::actingAs($this->teacher);

        $this->getJson('/api/v1/teacher-earnings')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonMissingPath('data.0.id')
            ->assertJsonPath('data.0.teacher_id', $this->teacher->public_id);

        $this->getJson('/api/v1/admin/teacher-earnings')->assertForbidden();
    }

    public function test_teacher_cannot_view_another_teachers_earnings(): void
    {
        $ownEarning = $this->createEarning($this->teacher);
        $otherEarning = $this->createEarning($this->otherTeacher);

        $this->teacher->givePermissionTo('teacher_earnings.view_own');

        Sanctum::actingAs($this->teacher);

        $this->getJson('/api/v1/teacher-earnings?teacher_id='.$this->otherTeacher->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonMissingPath('data.0.id')
            ->assertJsonMissing(['id' => $otherEarning->id]);

        $this->getJson("/api/v1/admin/teachers/{$this->otherTeacher->public_id}/teacher-earnings")
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createEarning(User $teacher, array $overrides = []): TeacherEarning
    {
        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole('student');

        $lessonRecord = LessonRecord::create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'scheduled_date' => $overrides['scheduled_date'] ?? '2026-06-10',
            'start_time' => '09:00:00',
            'end_time' => '10:30:00',
            'lesson_type' => $overrides['lesson_type'] ?? LessonRecord::TYPE_BUSINESS_ENGLISH,
            'lesson_status' => LessonRecord::STATUS_COMPLETED,
            'is_completed' => true,
        ]);

        return TeacherEarning::factory()
            ->forLessonRecord($lessonRecord)
            ->create([
                'pay_model' => $overrides['pay_model'] ?? TeacherCompensation::PAY_MODEL_PER_HOUR,
                'rate_used' => 40,
                'quantity' => 1.5,
                'amount' => 60,
                'currency' => 'USD',
                'status' => $overrides['status'] ?? TeacherEarning::STATUS_APPROVED,
                'calculation_metadata' => [
                    'lesson_type' => $lessonRecord->lesson_type,
                    'scheduled_date' => $lessonRecord->scheduled_date->toDateString(),
                    'course_program_id' => $overrides['course_program_id'] ?? null,
                    'course_type_id' => $this->courseProgram->course_type_id,
                    'payout_period' => $overrides['payout_period'] ?? '2026-06',
                    'internal_admin_notes' => $overrides['internal_admin_notes'] ?? null,
                ],
            ]);
    }
}
