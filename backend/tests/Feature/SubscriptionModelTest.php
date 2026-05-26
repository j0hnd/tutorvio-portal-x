<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\StudentProfile;
use App\Models\Subscription;
use App\Models\SubscriptionHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SubscriptionModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_stores_student_package_tracking_fields(): void
    {
        $student = User::factory()->create();
        $admin = User::factory()->create();
        $invoice = Invoice::factory()->create(['student_id' => $student->id]);
        $startsAt = Carbon::parse('2026-05-01 09:00:00');
        $endsAt = Carbon::parse('2026-06-01 09:00:00');
        $frozenAt = Carbon::parse('2026-05-15 09:00:00');

        $subscription = Subscription::create([
            'user_id' => $student->id,
            'plan_name' => 'Intensive 20 Lessons',
            'package_type' => Subscription::TYPE_PACKAGE,
            'total_lesson_count' => 20,
            'consumed_lesson_count' => 7,
            'remaining_lesson_count' => 13,
            'status' => Subscription::STATUS_INACTIVE,
            'is_frozen' => true,
            'frozen_at' => $frozenAt,
            'payment_status' => Subscription::PAYMENT_STATUS_PARTIAL,
            'invoice_id' => $invoice->id,
            'invoice_reference' => 'INV-2026-0001',
            'internal_notes' => 'Hold while student travels.',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $student->id,
            'plan_name' => 'Intensive 20 Lessons',
            'package_type' => Subscription::TYPE_PACKAGE,
            'total_lesson_count' => 20,
            'consumed_lesson_count' => 7,
            'remaining_lesson_count' => 13,
            'status' => Subscription::STATUS_INACTIVE,
            'payment_status' => Subscription::PAYMENT_STATUS_PARTIAL,
            'invoice_reference' => 'INV-2026-0001',
        ]);

        $this->assertTrue($subscription->student->is($student));
        $this->assertTrue($subscription->invoice->is($invoice));
        $this->assertTrue($subscription->createdBy->is($admin));
        $this->assertTrue($subscription->updatedBy->is($admin));
        $this->assertTrue($subscription->is_frozen);
        $this->assertTrue($subscription->frozen_at->equalTo($frozenAt));
        $this->assertTrue($subscription->starts_at->equalTo($startsAt));
        $this->assertTrue($subscription->ends_at->equalTo($endsAt));
    }

    public function test_subscription_history_tracks_package_audit_events(): void
    {
        $student = User::factory()->create();
        $studentProfile = StudentProfile::create(['user_id' => $student->id]);
        $admin = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $student->id,
            'plan_name' => 'Starter',
            'total_lesson_count' => 12,
            'consumed_lesson_count' => 2,
            'remaining_lesson_count' => 10,
        ]);

        $history = SubscriptionHistory::create([
            'subscription_id' => $subscription->id,
            'student_id' => $student->id,
            'event_type' => SubscriptionHistory::EVENT_FROZEN,
            'plan_name' => $subscription->plan_name,
            'package_type' => $subscription->package_type,
            'total_lesson_count' => $subscription->total_lesson_count,
            'consumed_lesson_count' => $subscription->consumed_lesson_count,
            'remaining_lesson_count' => $subscription->remaining_lesson_count,
            'status' => Subscription::STATUS_INACTIVE,
            'is_frozen' => true,
            'payment_status' => $subscription->payment_status,
            'starts_at' => $subscription->starts_at,
            'ends_at' => $subscription->ends_at,
            'previous_values' => [
                'is_frozen' => false,
                'status' => Subscription::STATUS_ACTIVE,
            ],
            'new_values' => [
                'is_frozen' => true,
                'status' => Subscription::STATUS_INACTIVE,
            ],
            'notes' => 'Student requested a one-week hold.',
            'effective_at' => Carbon::parse('2026-05-20 10:00:00'),
            'created_by' => $admin->id,
        ]);

        $this->assertTrue($history->subscription->is($subscription));
        $this->assertTrue($history->student->is($student));
        $this->assertTrue($history->createdBy->is($admin));
        $this->assertTrue($subscription->histories->first()->is($history));
        $this->assertTrue($student->subscriptionHistories->first()->is($history));
        $this->assertTrue($studentProfile->subscriptionHistories->first()->is($history));
        $this->assertSame(false, $history->previous_values['is_frozen']);
        $this->assertSame(true, $history->new_values['is_frozen']);
        $this->assertSame(12, $history->total_lesson_count);
        $this->assertTrue($history->is_frozen);
    }

    public function test_subscription_constants_match_supported_values(): void
    {
        $this->assertSame([
            Subscription::TYPE_PACKAGE,
            Subscription::TYPE_SUBSCRIPTION,
        ], Subscription::TYPES);

        $this->assertSame([
            Subscription::STATUS_ACTIVE,
            Subscription::STATUS_INACTIVE,
            Subscription::STATUS_EXPIRED,
            Subscription::STATUS_CANCELLED,
        ], Subscription::STATUSES);

        $this->assertSame([
            Subscription::PAYMENT_STATUS_PAID,
            Subscription::PAYMENT_STATUS_UNPAID,
            Subscription::PAYMENT_STATUS_PARTIAL,
            Subscription::PAYMENT_STATUS_OVERDUE,
        ], Subscription::PAYMENT_STATUSES);

        $this->assertSame([
            Subscription::RENEWAL_REMINDER_STATUS_NONE,
            Subscription::RENEWAL_REMINDER_STATUS_PENDING,
            Subscription::RENEWAL_REMINDER_STATUS_SENT,
            Subscription::RENEWAL_REMINDER_STATUS_NOT_ELIGIBLE,
        ], Subscription::RENEWAL_REMINDER_STATUSES);

        $this->assertSame([
            SubscriptionHistory::EVENT_ASSIGNED,
            SubscriptionHistory::EVENT_RENEWED,
            SubscriptionHistory::EVENT_FROZEN,
            SubscriptionHistory::EVENT_UNFROZEN,
            SubscriptionHistory::EVENT_STATUS_CHANGED,
            SubscriptionHistory::EVENT_PAYMENT_CHANGED,
            SubscriptionHistory::EVENT_INVOICE_REFERENCE_CHANGED,
            SubscriptionHistory::EVENT_LESSONS_CONSUMED,
            SubscriptionHistory::EVENT_MANUAL_BALANCE_ADJUSTED,
            SubscriptionHistory::EVENT_UPDATED,
            SubscriptionHistory::EVENT_CANCELLED,
            SubscriptionHistory::EVENT_ARCHIVED,
        ], SubscriptionHistory::EVENTS);
    }
}
