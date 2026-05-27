<?php

namespace Tests\Feature;

use App\Models\CourseType;
use App\Models\TeacherCompensation;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminTeacherCompensationApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->admin->assignRole('admin');

        $this->teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->teacher->assignRole('teacher');

        Sanctum::actingAs($this->admin);
    }

    public function test_admin_can_create_list_view_update_and_archive_teacher_compensation(): void
    {
        $createResponse = $this->postJson('/api/v1/admin/teacher-compensations', [
            'teacher_id' => $this->teacher->id,
            'pay_model' => TeacherCompensation::PAY_MODEL_PER_HOUR,
            'base_rate' => 30.50,
            'currency' => 'usd',
            'effective_start_date' => '2026-06-01',
            'effective_end_date' => '2026-06-30',
            'internal_admin_notes' => 'Initial payroll agreement.',
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.teacher_id', $this->teacher->id)
            ->assertJsonPath('data.pay_model', TeacherCompensation::PAY_MODEL_PER_HOUR)
            ->assertJsonPath('data.base_rate', '30.50')
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.internal_admin_notes', 'Initial payroll agreement.');

        $compensationId = $createResponse->json('data.id');

        $this->getJson('/api/v1/admin/teacher-compensations?teacher_id='.$this->teacher->id)
            ->assertOk()
            ->assertJsonPath('data.0.id', $compensationId);

        $this->getJson("/api/v1/admin/teachers/{$this->teacher->id}/teacher-compensations")
            ->assertOk()
            ->assertJsonPath('data.0.id', $compensationId);

        $this->patchJson("/api/v1/admin/teacher-compensations/{$compensationId}", [
            'pay_model' => TeacherCompensation::PAY_MODEL_PER_LESSON,
            'base_rate' => 35,
            'effective_end_date' => '2026-07-31',
        ])
            ->assertOk()
            ->assertJsonPath('data.pay_model', TeacherCompensation::PAY_MODEL_PER_LESSON)
            ->assertJsonPath('data.base_rate', '35.00');

        $this->postJson("/api/v1/admin/teacher-compensations/{$compensationId}/archive")
            ->assertOk()
            ->assertJsonPath('data.archived_by', $this->admin->id);

        $this->assertDatabaseHas('teacher_compensations', [
            'id' => $compensationId,
            'archived_by' => $this->admin->id,
        ]);
    }

    public function test_compensation_validation_rejects_invalid_teacher_rates_dates_and_overlaps(): void
    {
        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole('student');

        TeacherCompensation::factory()->create([
            'teacher_id' => $this->teacher->id,
            'effective_start_date' => '2026-06-01',
            'effective_end_date' => '2026-06-30',
        ]);

        $this->postJson('/api/v1/admin/teacher-compensations', [
            'teacher_id' => $student->id,
            'pay_model' => 'salary',
            'base_rate' => -1,
            'currency' => '',
            'effective_start_date' => '2026-07-10',
            'effective_end_date' => '2026-07-01',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'teacher_id',
                'pay_model',
                'default_pay_rate',
                'currency',
                'effective_end_date',
            ]);

        $this->postJson('/api/v1/admin/teacher-compensations', [
            'teacher_id' => $this->teacher->id,
            'pay_model' => TeacherCompensation::PAY_MODEL_PER_HOUR,
            'base_rate' => 40,
            'currency' => 'USD',
            'effective_start_date' => '2026-06-15',
            'effective_end_date' => '2026-07-15',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('effective_start_date');
    }

    public function test_admin_can_manage_variable_rate_rules_for_compensation(): void
    {
        $courseType = CourseType::factory()->create(['name' => 'Business English']);
        $compensation = TeacherCompensation::factory()->create([
            'teacher_id' => $this->teacher->id,
            'pay_model' => TeacherCompensation::PAY_MODEL_PER_HOUR,
            'default_pay_rate' => 25,
        ]);

        $createResponse = $this->postJson("/api/v1/admin/teacher-compensations/{$compensation->id}/rate-rules", [
            'lesson_type' => 'business_english',
            'experience_level' => 'senior',
            'contract_agreement' => 'premium',
            'course_type_id' => $courseType->id,
            'pay_model' => TeacherCompensation::PAY_MODEL_PER_LESSON,
            'pay_rate' => 55.75,
            'currency' => 'eur',
            'priority' => 20,
            'internal_admin_notes' => 'Premium override.',
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.lesson_type_override_rate', '55.75')
            ->assertJsonPath('data.experience_level_override_rate', '55.75')
            ->assertJsonPath('data.contract_agreement_override_rate', '55.75')
            ->assertJsonPath('data.course_override_rate', '55.75')
            ->assertJsonPath('data.currency', 'EUR');

        $ruleId = $createResponse->json('data.id');

        $this->getJson("/api/v1/admin/teacher-compensations/{$compensation->id}/rate-rules")
            ->assertOk()
            ->assertJsonPath('data.0.id', $ruleId);

        $this->patchJson("/api/v1/admin/teacher-compensations/{$compensation->id}/rate-rules/{$ruleId}", [
            'pay_rate' => 60,
            'priority' => 30,
        ])
            ->assertOk()
            ->assertJsonPath('data.pay_rate', '60.00')
            ->assertJsonPath('data.priority', 30);

        $this->postJson("/api/v1/admin/teacher-compensations/{$compensation->id}/rate-rules/{$ruleId}/archive")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_students_teachers_and_unpermitted_staff_cannot_access_teacher_compensations(): void
    {
        $compensation = TeacherCompensation::factory()->create([
            'teacher_id' => $this->teacher->id,
            'internal_admin_notes' => 'Private payroll note.',
        ]);

        Sanctum::actingAs($this->teacher);
        $this->getJson("/api/v1/admin/teacher-compensations/{$compensation->id}")
            ->assertForbidden()
            ->assertJsonMissing(['internal_admin_notes' => 'Private payroll note.']);

        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole('student');

        Sanctum::actingAs($student);
        $this->getJson('/api/v1/admin/teacher-compensations')
            ->assertForbidden()
            ->assertJsonMissing(['internal_admin_notes' => 'Private payroll note.']);

        $staff = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('staff');

        Sanctum::actingAs($staff);
        $this->getJson('/api/v1/admin/teacher-compensations')
            ->assertForbidden()
            ->assertJsonMissing(['internal_admin_notes' => 'Private payroll note.']);
    }

    public function test_staff_with_payroll_permission_can_access_and_manage_compensation_settings(): void
    {
        $staff = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('staff');
        $staff->givePermissionTo('teacher_compensations.view', 'teacher_compensations.manage');

        Sanctum::actingAs($staff);

        $this->postJson('/api/v1/admin/teacher-compensations', [
            'teacher_id' => $this->teacher->id,
            'pay_model' => TeacherCompensation::PAY_MODEL_PER_STUDENT,
            'base_rate' => 18,
            'currency' => 'USD',
            'effective_start_date' => '2026-08-01',
        ])
            ->assertCreated()
            ->assertJsonPath('data.teacher_id', $this->teacher->id);
    }

    public function test_staff_with_view_only_payroll_permission_cannot_manage_compensation_settings(): void
    {
        $staff = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('staff');
        $staff->givePermissionTo('teacher_compensations.view');

        $compensation = TeacherCompensation::factory()->create([
            'teacher_id' => $this->teacher->id,
        ]);

        Sanctum::actingAs($staff);

        $this->getJson("/api/v1/admin/teacher-compensations/{$compensation->id}")
            ->assertOk();

        $this->patchJson("/api/v1/admin/teacher-compensations/{$compensation->id}", [
            'base_rate' => 20,
        ])->assertForbidden();
    }
}
