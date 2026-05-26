<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionRenewalReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SubscriptionRenewalReminderServiceTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionRenewalReminderService $reminders;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-05-26 10:00:00');

        $this->reminders = app(SubscriptionRenewalReminderService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_refresh_marks_packages_due_for_end_date_low_balance_and_status(): void
    {
        $student = User::factory()->create();
        $endingSoon = Subscription::factory()->create([
            'user_id' => $student->id,
            'remaining_lesson_count' => 8,
            'ends_at' => '2026-06-01 09:00:00',
        ]);
        $lowBalance = Subscription::factory()->create([
            'user_id' => $student->id,
            'remaining_lesson_count' => 2,
            'ends_at' => '2026-08-01 09:00:00',
        ]);
        $expired = Subscription::factory()->create([
            'user_id' => $student->id,
            'status' => Subscription::STATUS_EXPIRED,
            'remaining_lesson_count' => 8,
            'ends_at' => '2026-08-01 09:00:00',
        ]);
        $notYetDue = Subscription::factory()->create([
            'user_id' => $student->id,
            'remaining_lesson_count' => 8,
            'ends_at' => '2026-08-01 09:00:00',
        ]);

        $this->assertSame(3, $this->reminders->refreshDueCandidates());

        $this->assertSame(Subscription::RENEWAL_REMINDER_STATUS_PENDING, $endingSoon->refresh()->renewal_reminder_status);
        $this->assertTrue($endingSoon->renewal_reminder_due_at->isSameDay('2026-05-25'));
        $this->assertStringContainsString('ending:2026-06-01', $endingSoon->renewal_reminder_window_key);

        $this->assertSame(Subscription::RENEWAL_REMINDER_STATUS_PENDING, $lowBalance->refresh()->renewal_reminder_status);
        $this->assertStringContainsString('low_balance:2', $lowBalance->renewal_reminder_window_key);

        $this->assertSame(Subscription::RENEWAL_REMINDER_STATUS_PENDING, $expired->refresh()->renewal_reminder_status);
        $this->assertStringContainsString('status:expired', $expired->renewal_reminder_window_key);

        $this->assertSame(Subscription::RENEWAL_REMINDER_STATUS_NONE, $notYetDue->refresh()->renewal_reminder_status);
        $this->assertNull($notYetDue->renewal_reminder_due_at);
    }

    public function test_send_due_creates_one_notification_per_reminder_window(): void
    {
        $student = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $student->id,
            'plan_name' => 'Starter',
            'remaining_lesson_count' => 1,
            'ends_at' => '2026-08-01 09:00:00',
        ]);

        $this->reminders->refreshReminderState($subscription);

        $this->assertSame(1, $this->reminders->sendDue());
        $this->assertSame(0, $this->reminders->sendDue());

        $subscription->refresh();
        $this->assertSame(Subscription::RENEWAL_REMINDER_STATUS_SENT, $subscription->renewal_reminder_status);
        $this->assertNotNull($subscription->renewal_reminder_last_sent_at);

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', [
            'type' => Notification::TYPE_RENEWAL_REMINDER,
            'title' => 'Package renewal reminder',
        ]);
        $this->assertDatabaseHas('notification_recipients', [
            'user_id' => $student->id,
        ]);
    }

    public function test_cancelled_ineligible_or_already_renewed_packages_are_not_due(): void
    {
        $student = User::factory()->create();
        $cancelled = Subscription::factory()->create([
            'user_id' => $student->id,
            'status' => Subscription::STATUS_CANCELLED,
            'remaining_lesson_count' => 1,
        ]);
        $ineligible = Subscription::factory()->create([
            'user_id' => $student->id,
            'renewal_eligible' => false,
            'remaining_lesson_count' => 1,
        ]);
        $renewed = Subscription::factory()->create([
            'user_id' => $student->id,
            'remaining_lesson_count' => 1,
        ]);
        Subscription::factory()->create([
            'user_id' => $student->id,
            'renewed_from_subscription_id' => $renewed->id,
        ]);

        $this->assertSame(
            Subscription::RENEWAL_REMINDER_STATUS_NOT_ELIGIBLE,
            $this->reminders->refreshReminderState($cancelled)->renewal_reminder_status
        );
        $this->assertSame(
            Subscription::RENEWAL_REMINDER_STATUS_NOT_ELIGIBLE,
            $this->reminders->refreshReminderState($ineligible)->renewal_reminder_status
        );
        $this->assertSame(
            Subscription::RENEWAL_REMINDER_STATUS_NOT_ELIGIBLE,
            $this->reminders->refreshReminderState($renewed)->renewal_reminder_status
        );
    }
}
