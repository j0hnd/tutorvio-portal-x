<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\SubscriptionHistory;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminSubscriptionManagementApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);

        Carbon::setTestNow('2026-05-26 10:00:00');

        $this->admin = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->admin->assignRole('admin');

        Sanctum::actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_can_assign_view_update_and_cancel_subscription(): void
    {
        $student = $this->student();

        $createResponse = $this->postJson('/api/v1/admin/subscriptions', [
            'student_id' => $student->id,
            'plan_name' => 'Intensive 20 Lessons',
            'package_type' => Subscription::TYPE_PACKAGE,
            'total_lesson_count' => 20,
            'consumed_lesson_count' => 2,
            'remaining_lesson_count' => 18,
            'status' => Subscription::STATUS_ACTIVE,
            'payment_status' => Subscription::PAYMENT_STATUS_PARTIAL,
            'invoice_reference' => 'INV-2026-0001',
            'internal_notes' => 'Admin only note.',
            'starts_at' => '2026-05-01',
            'ends_at' => '2026-06-01',
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.student_id', $student->id)
            ->assertJsonPath('data.internal_notes', 'Admin only note.')
            ->assertJsonPath('data.created_by', $this->admin->id);

        $subscriptionId = $createResponse->json('data.id');

        $this->assertDatabaseHas('subscription_histories', [
            'subscription_id' => $subscriptionId,
            'student_id' => $student->id,
            'event_type' => SubscriptionHistory::EVENT_ASSIGNED,
            'created_by' => $this->admin->id,
        ]);

        $this->getJson('/api/v1/admin/subscriptions?package_type=package&payment_status=partial&search=Intensive')
            ->assertOk()
            ->assertJsonPath('data.0.id', $subscriptionId);

        $this->getJson("/api/v1/admin/subscriptions/{$subscriptionId}")
            ->assertOk()
            ->assertJsonPath('data.invoice_reference', 'INV-2026-0001')
            ->assertJsonPath('data.internal_notes', 'Admin only note.');

        $this->patchJson("/api/v1/admin/subscriptions/{$subscriptionId}", [
            'total_lesson_count' => 24,
            'consumed_lesson_count' => 4,
            'remaining_lesson_count' => 20,
            'ends_at' => '2026-07-01',
        ])
            ->assertOk()
            ->assertJsonPath('data.total_lesson_count', 24)
            ->assertJsonPath('data.remaining_lesson_count', 20);

        $this->postJson("/api/v1/admin/subscriptions/{$subscriptionId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', Subscription::STATUS_CANCELLED);

        $this->assertDatabaseHas('subscription_histories', [
            'subscription_id' => $subscriptionId,
            'event_type' => SubscriptionHistory::EVENT_UPDATED,
        ]);
        $this->assertDatabaseHas('subscription_histories', [
            'subscription_id' => $subscriptionId,
            'event_type' => SubscriptionHistory::EVENT_CANCELLED,
        ]);
    }

    public function test_admin_can_manage_status_freeze_notes_invoice_reference_and_renewal(): void
    {
        $student = $this->student();
        $subscription = Subscription::factory()->create([
            'user_id' => $student->id,
            'payment_status' => Subscription::PAYMENT_STATUS_UNPAID,
        ]);

        $this->patchJson("/api/v1/admin/subscriptions/{$subscription->id}/payment-status", [
            'payment_status' => Subscription::PAYMENT_STATUS_PAID,
        ])
            ->assertOk()
            ->assertJsonPath('data.payment_status', Subscription::PAYMENT_STATUS_PAID);

        $this->patchJson("/api/v1/admin/subscriptions/{$subscription->id}/status", [
            'status' => Subscription::STATUS_INACTIVE,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', Subscription::STATUS_INACTIVE);

        $this->postJson("/api/v1/admin/subscriptions/{$subscription->id}/freeze")
            ->assertOk()
            ->assertJsonPath('data.is_frozen', true)
            ->assertJsonPath('data.status', Subscription::STATUS_INACTIVE);

        $this->postJson("/api/v1/admin/subscriptions/{$subscription->id}/unfreeze")
            ->assertOk()
            ->assertJsonPath('data.is_frozen', false)
            ->assertJsonPath('data.status', Subscription::STATUS_ACTIVE);

        $this->patchJson("/api/v1/admin/subscriptions/{$subscription->id}/notes", [
            'notes' => 'Renewal discussed by phone.',
        ])
            ->assertOk()
            ->assertJsonPath('data.internal_notes', 'Renewal discussed by phone.');

        $this->patchJson("/api/v1/admin/subscriptions/{$subscription->id}/invoice-reference", [
            'reference' => 'INV-RENEW-0001',
        ])
            ->assertOk()
            ->assertJsonPath('data.invoice_reference', 'INV-RENEW-0001');

        $renewalResponse = $this->postJson("/api/v1/admin/subscriptions/{$subscription->id}/renew", [
            'student_id' => $student->id,
            'plan_name' => 'Renewed Standard',
            'package_type' => Subscription::TYPE_SUBSCRIPTION,
            'total_lesson_count' => 12,
            'consumed_lesson_count' => 0,
            'remaining_lesson_count' => 12,
            'status' => Subscription::STATUS_ACTIVE,
            'payment_status' => Subscription::PAYMENT_STATUS_UNPAID,
            'starts_at' => '2026-06-01',
            'ends_at' => '2026-07-01',
        ]);

        $renewalResponse
            ->assertCreated()
            ->assertJsonPath('data.renewed_from_subscription_id', $subscription->id);

        $this->getJson("/api/v1/admin/students/{$student->id}/subscriptions/history")
            ->assertOk()
            ->assertJsonFragment(['event_type' => SubscriptionHistory::EVENT_RENEWED])
            ->assertJsonFragment(['event_type' => SubscriptionHistory::EVENT_FROZEN])
            ->assertJsonFragment(['event_type' => SubscriptionHistory::EVENT_UNFROZEN]);
    }

    public function test_admin_can_manually_adjust_lesson_balance_with_required_notes(): void
    {
        $student = $this->student();
        $subscription = Subscription::factory()->create([
            'user_id' => $student->id,
            'total_lesson_count' => 12,
            'consumed_lesson_count' => 4,
            'remaining_lesson_count' => 8,
        ]);

        $this->patchJson("/api/v1/admin/subscriptions/{$subscription->id}/lesson-balance", [
            'consumed_lesson_count' => 5,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('notes');

        $this->patchJson("/api/v1/admin/subscriptions/{$subscription->id}/lesson-balance", [
            'total_lesson_count' => 12,
            'consumed_lesson_count' => 5,
            'remaining_lesson_count' => 8,
            'notes' => 'Corrected one missed completion entry.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('remaining_lesson_count');

        $this->patchJson("/api/v1/admin/subscriptions/{$subscription->id}/lesson-balance", [
            'consumed_lesson_count' => 5,
            'notes' => 'Corrected one missed completion entry.',
        ])
            ->assertOk()
            ->assertJsonPath('data.total_lesson_count', 12)
            ->assertJsonPath('data.consumed_lesson_count', 5)
            ->assertJsonPath('data.remaining_lesson_count', 7);

        $this->assertDatabaseHas('subscription_histories', [
            'subscription_id' => $subscription->id,
            'event_type' => SubscriptionHistory::EVENT_MANUAL_BALANCE_ADJUSTED,
            'notes' => 'Corrected one missed completion entry.',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_admin_lesson_balance_adjustment_rejects_over_consumption_and_negative_remaining(): void
    {
        $student = $this->student();
        $subscription = Subscription::factory()->create([
            'user_id' => $student->id,
            'total_lesson_count' => 5,
            'consumed_lesson_count' => 2,
            'remaining_lesson_count' => 3,
        ]);

        $this->patchJson("/api/v1/admin/subscriptions/{$subscription->id}/lesson-balance", [
            'consumed_lesson_count' => 6,
            'notes' => 'Invalid correction that over-consumes lessons.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('consumed_lesson_count');

        $this->patchJson("/api/v1/admin/subscriptions/{$subscription->id}/lesson-balance", [
            'remaining_lesson_count' => -1,
            'notes' => 'Invalid correction with negative remaining lessons.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('remaining_lesson_count');

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'total_lesson_count' => 5,
            'consumed_lesson_count' => 2,
            'remaining_lesson_count' => 3,
        ]);
        $this->assertDatabaseMissing('subscription_histories', [
            'subscription_id' => $subscription->id,
            'event_type' => SubscriptionHistory::EVENT_MANUAL_BALANCE_ADJUSTED,
        ]);
    }

    public function test_subscription_validation_rejects_invalid_student_lessons_dates_and_statuses(): void
    {
        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $teacher->assignRole('teacher');

        $this->postJson('/api/v1/admin/subscriptions', [
            'student_id' => $teacher->id,
            'plan_name' => 'Invalid',
            'package_type' => Subscription::TYPE_PACKAGE,
            'total_lesson_count' => 10,
            'consumed_lesson_count' => 11,
            'remaining_lesson_count' => 12,
            'status' => 'paused',
            'payment_status' => 'pending',
            'starts_at' => '2026-06-01',
            'ends_at' => '2026-05-01',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'student_id',
                'consumed_lesson_count',
                'remaining_lesson_count',
                'status',
                'payment_status',
                'ends_at',
            ]);
    }

    public function test_students_and_teachers_cannot_access_admin_subscription_notes(): void
    {
        $student = $this->student();
        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $teacher->assignRole('teacher');
        $subscription = Subscription::factory()->create([
            'user_id' => $student->id,
            'internal_notes' => 'Private admin note.',
        ]);

        Sanctum::actingAs($student);

        $this->getJson("/api/v1/admin/subscriptions/{$subscription->id}")
            ->assertForbidden()
            ->assertJsonMissing(['internal_notes' => 'Private admin note.']);

        Sanctum::actingAs($teacher);

        $this->getJson("/api/v1/admin/subscriptions/{$subscription->id}")
            ->assertForbidden()
            ->assertJsonMissing(['internal_notes' => 'Private admin note.']);
    }

    public function test_staff_without_billing_permission_cannot_access_or_manage_subscriptions(): void
    {
        $staff = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('staff');
        $student = $this->student();
        $subscription = Subscription::factory()->create([
            'user_id' => $student->id,
            'payment_status' => Subscription::PAYMENT_STATUS_OVERDUE,
            'invoice_reference' => 'INV-STAFF-HIDDEN',
            'internal_notes' => 'Staff without billing permission must not see this.',
        ]);

        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/admin/subscriptions')
            ->assertForbidden()
            ->assertJsonMissing(['invoice_reference' => 'INV-STAFF-HIDDEN'])
            ->assertJsonMissing(['internal_notes' => 'Staff without billing permission must not see this.']);

        $this->getJson("/api/v1/admin/subscriptions/{$subscription->id}")
            ->assertForbidden()
            ->assertJsonMissing(['invoice_reference' => 'INV-STAFF-HIDDEN'])
            ->assertJsonMissing(['internal_notes' => 'Staff without billing permission must not see this.']);

        $this->patchJson("/api/v1/admin/subscriptions/{$subscription->id}/payment-status", [
            'payment_status' => Subscription::PAYMENT_STATUS_PAID,
        ])->assertForbidden();

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'payment_status' => Subscription::PAYMENT_STATUS_OVERDUE,
        ]);
    }

    public function test_staff_with_billing_permission_can_access_subscription_billing_fields(): void
    {
        $staff = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('staff');
        $staff->givePermissionTo('subscriptions.view');

        $subscription = Subscription::factory()->create([
            'user_id' => $this->student()->id,
            'payment_status' => Subscription::PAYMENT_STATUS_PARTIAL,
            'invoice_reference' => 'INV-STAFF-VISIBLE',
            'internal_notes' => 'Staff billing note.',
        ]);

        Sanctum::actingAs($staff);

        $this->getJson("/api/v1/admin/subscriptions/{$subscription->id}")
            ->assertOk()
            ->assertJsonPath('data.payment_status', Subscription::PAYMENT_STATUS_PARTIAL)
            ->assertJsonPath('data.invoice_reference', 'INV-STAFF-VISIBLE')
            ->assertJsonPath('data.internal_notes', 'Staff billing note.');
    }

    private function student(): User
    {
        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole('student');

        return $student;
    }
}
