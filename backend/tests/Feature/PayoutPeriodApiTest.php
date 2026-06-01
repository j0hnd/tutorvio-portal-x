<?php

namespace Tests\Feature;

use App\Models\LessonRecord;
use App\Models\PayoutPeriod;
use App\Models\TeacherCompensation;
use App\Models\TeacherEarning;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PayoutPeriodApiTest extends TestCase
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

    public function test_admin_can_create_payout_period_and_group_eligible_earnings(): void
    {
        $eligible = $this->createEarning('2026-06-10', TeacherEarning::STATUS_APPROVED);
        $pending = $this->createEarning('2026-06-11', TeacherEarning::STATUS_PENDING);
        $outsidePeriod = $this->createEarning('2026-07-01', TeacherEarning::STATUS_APPROVED);

        $response = $this->postJson('/api/v1/admin/payout-periods', [
            'name' => 'June first half',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-15',
            'cutoff_date' => '2026-06-16',
            'payout_date' => '2026-06-20',
            'status' => PayoutPeriod::STATUS_OPEN,
            'notes' => 'First half payout.',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'June first half')
            ->assertJsonPath('data.status', PayoutPeriod::STATUS_OPEN)
            ->assertJsonPath('data.earnings_count', 1)
            ->assertJsonPath('data.earnings.0.id', $eligible->id);

        $periodPublicId = $response->json('data.id');
        $periodId = PayoutPeriod::where('public_id', $periodPublicId)->value('id');

        $this->assertDatabaseHas('payout_period_teacher_earning', [
            'payout_period_id' => $periodId,
            'teacher_earning_id' => $eligible->id,
        ]);
        $this->assertDatabaseMissing('payout_period_teacher_earning', [
            'payout_period_id' => $periodId,
            'teacher_earning_id' => $pending->id,
        ]);
        $this->assertDatabaseMissing('payout_period_teacher_earning', [
            'payout_period_id' => $periodId,
            'teacher_earning_id' => $outsidePeriod->id,
        ]);
        $this->assertDatabaseHas('teacher_earnings', [
            'id' => $eligible->id,
            'status' => TeacherEarning::STATUS_INCLUDED_IN_PAYOUT,
        ]);
    }

    public function test_earning_already_in_active_payout_period_is_not_included_again(): void
    {
        $earning = $this->createEarning('2026-06-10', TeacherEarning::STATUS_APPROVED);

        $firstPeriod = PayoutPeriod::factory()->create([
            'name' => 'June first half',
            'status' => PayoutPeriod::STATUS_OPEN,
        ]);
        $firstPeriod->earnings()->attach($earning);
        $earning->update(['status' => TeacherEarning::STATUS_INCLUDED_IN_PAYOUT]);

        $this->postJson('/api/v1/admin/payout-periods', [
            'name' => 'June duplicate',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-15',
            'cutoff_date' => '2026-06-16',
            'payout_date' => '2026-06-20',
        ])
            ->assertCreated()
            ->assertJsonPath('data.earnings_count', 0);
    }

    public function test_draft_and_open_periods_can_update_and_refresh_earnings_but_locked_periods_restrict_changes(): void
    {
        $included = $this->createEarning('2026-06-10', TeacherEarning::STATUS_APPROVED);
        $addedAfterDateChange = $this->createEarning('2026-06-20', TeacherEarning::STATUS_APPROVED);

        $periodPublicId = $this->postJson('/api/v1/admin/payout-periods', [
            'name' => 'June first half',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-15',
            'cutoff_date' => '2026-06-16',
            'payout_date' => '2026-06-20',
        ])->json('data.id');
        $periodId = PayoutPeriod::where('public_id', $periodPublicId)->value('id');

        $this->patchJson("/api/v1/admin/payout-periods/{$periodPublicId}", [
            'end_date' => '2026-06-20',
            'cutoff_date' => '2026-06-21',
            'payout_date' => '2026-06-25',
        ])
            ->assertOk()
            ->assertJsonPath('data.earnings_count', 2);

        $this->assertDatabaseHas('payout_period_teacher_earning', [
            'payout_period_id' => $periodId,
            'teacher_earning_id' => $included->id,
        ]);
        $this->assertDatabaseHas('payout_period_teacher_earning', [
            'payout_period_id' => $periodId,
            'teacher_earning_id' => $addedAfterDateChange->id,
        ]);

        $this->patchJson("/api/v1/admin/payout-periods/{$periodPublicId}", [
            'status' => PayoutPeriod::STATUS_LOCKED,
        ])->assertOk();

        $this->patchJson("/api/v1/admin/payout-periods/{$periodPublicId}", [
            'end_date' => '2026-06-25',
        ])->assertUnprocessable();

        $this->patchJson("/api/v1/admin/payout-periods/{$periodPublicId}", [
            'status' => PayoutPeriod::STATUS_PAID,
        ])->assertOk();

        $this->assertDatabaseHas('teacher_earnings', [
            'id' => $included->id,
            'status' => TeacherEarning::STATUS_PAID,
        ]);
    }

    public function test_cancelled_period_releases_earnings_and_is_not_used_for_new_calculations(): void
    {
        $earning = $this->createEarning('2026-06-10', TeacherEarning::STATUS_APPROVED);

        $periodId = $this->postJson('/api/v1/admin/payout-periods', [
            'name' => 'June first half',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-15',
            'cutoff_date' => '2026-06-16',
            'payout_date' => '2026-06-20',
        ])->json('data.id');

        $this->patchJson("/api/v1/admin/payout-periods/{$periodId}", [
            'status' => PayoutPeriod::STATUS_CANCELLED,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', PayoutPeriod::STATUS_CANCELLED)
            ->assertJsonPath('data.earnings_count', 0);

        $this->assertDatabaseMissing('payout_period_teacher_earning', [
            'payout_period_id' => $periodId,
            'teacher_earning_id' => $earning->id,
        ]);
        $this->assertDatabaseHas('teacher_earnings', [
            'id' => $earning->id,
            'status' => TeacherEarning::STATUS_APPROVED,
        ]);

        $this->postJson('/api/v1/admin/payout-periods', [
            'name' => 'June replacement',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-15',
            'cutoff_date' => '2026-06-16',
            'payout_date' => '2026-06-20',
        ])
            ->assertCreated()
            ->assertJsonPath('data.earnings_count', 1);
    }

    public function test_staff_requires_payout_period_permission_to_manage_periods(): void
    {
        $staff = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('staff');

        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/admin/payout-periods')->assertForbidden();

        $staff->givePermissionTo('payout_periods.view', 'payout_periods.manage');

        $this->postJson('/api/v1/admin/payout-periods', [
            'name' => 'June first half',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-15',
            'cutoff_date' => '2026-06-16',
            'payout_date' => '2026-06-20',
        ])->assertCreated();
    }

    private function createEarning(string $scheduledDate, string $status): TeacherEarning
    {
        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole('student');

        $lessonRecord = LessonRecord::create([
            'student_id' => $student->id,
            'teacher_id' => $this->teacher->id,
            'scheduled_date' => $scheduledDate,
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
                'rate_used' => 40,
                'quantity' => 1,
                'amount' => 40,
                'currency' => 'USD',
                'status' => $status,
            ]);
    }
}
