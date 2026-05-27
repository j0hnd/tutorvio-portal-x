<?php

namespace Tests\Feature;

use App\Models\LessonRecord;
use App\Models\PayoutPeriod;
use App\Models\TeacherCompensation;
use App\Models\TeacherEarning;
use App\Models\TeacherPayoutAdjustment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PayoutReportAndAdjustmentApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    private PayoutPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->admin->assignRole('admin');

        $this->teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->teacher->assignRole('teacher');

        $this->period = PayoutPeriod::factory()->create([
            'name' => 'June payout',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'cutoff_date' => '2026-07-01',
            'payout_date' => '2026-07-05',
            'status' => PayoutPeriod::STATUS_OPEN,
        ]);
    }

    public function test_admin_can_create_manual_adjustment_with_reason_notes_and_creator(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/admin/payout-adjustments', [
            'teacher_id' => $this->teacher->id,
            'payout_period_id' => $this->period->id,
            'type' => TeacherPayoutAdjustment::TYPE_DEDUCTION,
            'amount' => 15,
            'currency' => 'usd',
            'reason' => 'Correct overpaid lesson duration.',
            'internal_notes' => 'Verified against class log.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.teacher_id', $this->teacher->id)
            ->assertJsonPath('data.payout_period_id', $this->period->id)
            ->assertJsonPath('data.type', TeacherPayoutAdjustment::TYPE_DEDUCTION)
            ->assertJsonPath('data.amount', '-15.00')
            ->assertJsonPath('data.reason', 'Correct overpaid lesson duration.')
            ->assertJsonPath('data.internal_notes', 'Verified against class log.')
            ->assertJsonPath('data.created_by', $this->admin->id);

        $this->assertDatabaseHas('teacher_payout_adjustments', [
            'teacher_id' => $this->teacher->id,
            'payout_period_id' => $this->period->id,
            'amount' => -15,
            'currency' => 'USD',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_payout_report_by_period_includes_earnings_and_adjustment_breakdown(): void
    {
        $base = $this->createEarning(100);
        $variable = $this->createEarning(40, ['teacher_compensation_rate_rule_id' => 123]);
        $this->period->earnings()->attach([$base->id, $variable->id]);

        TeacherPayoutAdjustment::factory()->create([
            'teacher_id' => $this->teacher->id,
            'payout_period_id' => $this->period->id,
            'type' => TeacherPayoutAdjustment::TYPE_BONUS,
            'amount' => 25,
            'created_by' => $this->admin->id,
        ]);
        TeacherPayoutAdjustment::factory()->create([
            'teacher_id' => $this->teacher->id,
            'payout_period_id' => $this->period->id,
            'type' => TeacherPayoutAdjustment::TYPE_DEDUCTION,
            'amount' => -10,
            'created_by' => $this->admin->id,
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson("/api/v1/admin/payout-periods/{$this->period->id}/report")
            ->assertOk()
            ->assertJsonPath('data.scope', 'period')
            ->assertJsonPath('data.payout_period.id', $this->period->id)
            ->assertJsonPath('data.breakdown.base_earnings', 100)
            ->assertJsonPath('data.breakdown.variable_rate_earnings', 40)
            ->assertJsonPath('data.breakdown.manual_additions', 25)
            ->assertJsonPath('data.breakdown.manual_deductions', 10)
            ->assertJsonPath('data.breakdown.total_payout_amount', 155)
            ->assertJsonPath('data.teachers.0.teacher_id', $this->teacher->id)
            ->assertJsonPath('data.teachers.0.breakdown.total_payout_amount', 155);
    }

    public function test_admin_can_generate_payout_report_by_teacher(): void
    {
        $earning = $this->createEarning(80);
        $this->period->earnings()->attach($earning);

        TeacherPayoutAdjustment::factory()->create([
            'teacher_id' => $this->teacher->id,
            'payout_period_id' => $this->period->id,
            'type' => TeacherPayoutAdjustment::TYPE_REIMBURSEMENT,
            'amount' => 12,
            'created_by' => $this->admin->id,
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson("/api/v1/admin/teachers/{$this->teacher->id}/payout-report?payout_period_id={$this->period->id}")
            ->assertOk()
            ->assertJsonPath('data.scope', 'teacher')
            ->assertJsonPath('data.teacher.id', $this->teacher->id)
            ->assertJsonPath('data.breakdown.base_earnings', 80)
            ->assertJsonPath('data.breakdown.manual_additions', 12)
            ->assertJsonPath('data.breakdown.total_payout_amount', 92);
    }

    public function test_staff_requires_payroll_permission_and_students_are_denied(): void
    {
        $staff = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('staff');

        Sanctum::actingAs($staff);
        $this->getJson("/api/v1/admin/payout-periods/{$this->period->id}/report")->assertForbidden();
        $this->getJson('/api/v1/admin/payout-adjustments')->assertForbidden();

        $staff->givePermissionTo('payroll.view');

        $this->getJson("/api/v1/admin/payout-periods/{$this->period->id}/report")->assertOk();
        $this->getJson('/api/v1/admin/payout-adjustments')->assertOk();
        $this->postJson('/api/v1/admin/payout-adjustments', [
            'teacher_id' => $this->teacher->id,
            'payout_period_id' => $this->period->id,
            'type' => TeacherPayoutAdjustment::TYPE_BONUS,
            'amount' => 10,
            'reason' => 'Approved bonus.',
        ])->assertForbidden();

        $staff->givePermissionTo('payroll.manage');

        $this->postJson('/api/v1/admin/payout-adjustments', [
            'teacher_id' => $this->teacher->id,
            'payout_period_id' => $this->period->id,
            'type' => TeacherPayoutAdjustment::TYPE_BONUS,
            'amount' => 10,
            'reason' => 'Approved bonus.',
        ])->assertCreated();

        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole('student');

        Sanctum::actingAs($student);
        $this->getJson("/api/v1/admin/payout-periods/{$this->period->id}/report")->assertForbidden();
        $this->getJson("/api/v1/admin/teachers/{$this->teacher->id}/payout-report")->assertForbidden();
        $this->getJson('/api/v1/admin/payout-adjustments')->assertForbidden();
        $this->postJson('/api/v1/admin/payout-adjustments', [
            'teacher_id' => $this->teacher->id,
            'type' => TeacherPayoutAdjustment::TYPE_BONUS,
            'amount' => 10,
            'reason' => 'Student attempt.',
        ])->assertForbidden();
        $this->getJson('/api/v1/payroll-adjustments')->assertForbidden();
    }

    public function test_teacher_can_view_adjustment_summaries_only_when_enabled_without_internal_notes(): void
    {
        TeacherPayoutAdjustment::factory()->create([
            'teacher_id' => $this->teacher->id,
            'payout_period_id' => $this->period->id,
            'type' => TeacherPayoutAdjustment::TYPE_CORRECTION,
            'amount' => 18,
            'reason' => 'Lesson correction.',
            'internal_notes' => 'Admin-only context.',
            'created_by' => $this->admin->id,
        ]);

        Sanctum::actingAs($this->teacher);

        $this->getJson('/api/v1/payroll-adjustments')->assertForbidden();

        config(['teacher_earnings.teacher_payroll_visibility_enabled' => true]);

        $this->getJson('/api/v1/payroll-adjustments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reason', 'Lesson correction.')
            ->assertJsonMissing(['internal_notes' => 'Admin-only context.'])
            ->assertJsonMissing(['created_by' => $this->admin->id]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function createEarning(float $amount, array $metadata = []): TeacherEarning
    {
        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole('student');

        $lessonRecord = LessonRecord::create([
            'student_id' => $student->id,
            'teacher_id' => $this->teacher->id,
            'scheduled_date' => '2026-06-10',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'lesson_type' => LessonRecord::TYPE_BUSINESS_ENGLISH,
            'lesson_status' => LessonRecord::STATUS_COMPLETED,
            'is_completed' => true,
        ]);

        return TeacherEarning::factory()
            ->forLessonRecord($lessonRecord)
            ->create([
                'pay_model' => TeacherCompensation::PAY_MODEL_PER_HOUR,
                'rate_used' => $amount,
                'quantity' => 1,
                'amount' => $amount,
                'currency' => 'USD',
                'status' => TeacherEarning::STATUS_INCLUDED_IN_PAYOUT,
                'calculation_metadata' => [
                    'scheduled_date' => '2026-06-10',
                    ...$metadata,
                ],
            ]);
    }
}
