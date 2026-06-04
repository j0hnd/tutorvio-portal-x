<?php

namespace Tests\Feature;

use App\Jobs\Notifications\SendSystemNotificationEmail;
use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Models\User;
use App\Notifications\SystemNotificationEmail;
use App\Services\Notifications\SystemNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class SystemNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-15 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_creates_portal_notifications_for_single_and_multiple_recipients(): void
    {
        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);

        $notification = app(SystemNotificationService::class)->classReminder(
            [$student, $teacher->id],
            'Class starts soon',
            'Your class starts in 30 minutes.',
            ['lesson_id' => 123],
            ['source_type' => 'lesson', 'source_id' => 123]
        );

        $this->assertSame(Notification::TYPE_CLASS_REMINDER, $notification->type);
        $this->assertSame([
            'lesson_id' => 123,
            'source_type' => 'lesson',
            'source_id' => 123,
        ], $notification->metadata);

        $this->assertDatabaseHas('notification_recipients', [
            'notification_id' => $notification->id,
            'user_id' => $student->id,
            'channel' => NotificationRecipient::CHANNEL_IN_PORTAL,
            'delivery_status' => NotificationRecipient::STATUS_DELIVERED,
            'sent_at' => '2026-06-15 12:00:00',
            'delivered_at' => '2026-06-15 12:00:00',
        ]);
        $this->assertDatabaseHas('notification_recipients', [
            'notification_id' => $notification->id,
            'user_id' => $teacher->id,
            'channel' => NotificationRecipient::CHANNEL_IN_PORTAL,
            'delivery_status' => NotificationRecipient::STATUS_DELIVERED,
        ]);
    }

    public function test_it_reuses_notifications_for_the_same_source_event(): void
    {
        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $service = app(SystemNotificationService::class);

        $first = $service->rescheduleAlert(
            $student,
            'Schedule changed',
            'Your class was moved.',
            ['class_schedule_id' => 88],
            ['source_type' => 'class_schedule', 'source_id' => 88]
        );

        $second = $service->rescheduleAlert(
            [$student, $teacher],
            'Schedule changed again',
            'Your class was moved to a new time.',
            ['class_schedule_id' => 88],
            ['source_type' => 'class_schedule', 'source_id' => 88]
        );

        $this->assertTrue($first->is($second));
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseCount('notification_recipients', 2);
        $this->assertDatabaseHas('notifications', [
            'id' => $first->id,
            'title' => 'Schedule changed again',
            'body' => 'Your class was moved to a new time.',
        ]);
    }

    public function test_it_optionally_dispatches_email_notifications_once_per_source_event(): void
    {
        NotificationFacade::fake();

        $student = User::factory()->create([
            'email' => 'student@example.test',
            'status' => User::STATUS_ACTIVE,
        ]);
        $teacher = User::factory()->create([
            'email' => 'teacher@example.test',
            'status' => User::STATUS_ACTIVE,
        ]);
        $service = app(SystemNotificationService::class);

        $service->homeworkReminder(
            [$student, $teacher],
            'Homework due soon',
            'Please submit your homework before the deadline.',
            ['homework_id' => 456],
            [
                'email' => true,
                'source_type' => 'homework',
                'source_id' => 456,
            ]
        );
        $service->homeworkReminder(
            [$student, $teacher],
            'Homework due soon',
            'Please submit your homework before the deadline.',
            ['homework_id' => 456],
            [
                'email' => true,
                'source_type' => 'homework',
                'source_id' => 456,
            ]
        );

        NotificationFacade::assertSentTo($student, SystemNotificationEmail::class);
        NotificationFacade::assertSentTo($teacher, SystemNotificationEmail::class);
        NotificationFacade::assertSentTimes(SystemNotificationEmail::class, 2);

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseCount('notification_recipients', 4);
        $this->assertDatabaseHas('notification_recipients', [
            'user_id' => $student->id,
            'channel' => NotificationRecipient::CHANNEL_EMAIL,
            'delivery_status' => NotificationRecipient::STATUS_SENT,
        ]);
    }

    public function test_it_queues_email_notifications_when_requested(): void
    {
        Queue::fake();
        NotificationFacade::fake();

        $student = User::factory()->create([
            'email' => 'student@example.test',
            'status' => User::STATUS_ACTIVE,
        ]);
        $teacher = User::factory()->create([
            'email' => 'teacher@example.test',
            'status' => User::STATUS_ACTIVE,
        ]);
        $service = app(SystemNotificationService::class);

        $service->homeworkReminder(
            [$student, $teacher],
            'Homework due soon',
            'Please submit your homework before the deadline.',
            ['homework_id' => 456],
            [
                'email' => true,
                'queue_email' => true,
                'source_type' => 'homework',
                'source_id' => 456,
            ]
        );
        Queue::assertPushed(SendSystemNotificationEmail::class, 2);
        NotificationFacade::assertNothingSent();

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseCount('notification_recipients', 4);
        $this->assertSame(
            2,
            NotificationRecipient::query()
                ->where('channel', NotificationRecipient::CHANNEL_EMAIL)
                ->where('delivery_status', NotificationRecipient::STATUS_PENDING)
                ->count()
        );
    }

    public function test_email_failure_does_not_prevent_in_portal_notification_creation(): void
    {
        $student = User::factory()->create([
            'email' => 'student@example.test',
            'status' => User::STATUS_ACTIVE,
        ]);

        NotificationFacade::shouldReceive('send')
            ->once()
            ->andThrow(new RuntimeException('SMTP connection failed.'));

        $notification = app(SystemNotificationService::class)->adminAnnouncement(
            $student,
            'Schedule update',
            'Your schedule has changed.',
            ['announcement_id' => 321],
            [
                'email' => true,
                'source_type' => 'announcement',
                'source_id' => 321,
            ]
        );

        $this->assertDatabaseHas('notification_recipients', [
            'notification_id' => $notification->id,
            'user_id' => $student->id,
            'channel' => NotificationRecipient::CHANNEL_IN_PORTAL,
            'delivery_status' => NotificationRecipient::STATUS_DELIVERED,
        ]);
        $this->assertDatabaseHas('notification_recipients', [
            'notification_id' => $notification->id,
            'user_id' => $student->id,
            'channel' => NotificationRecipient::CHANNEL_EMAIL,
            'delivery_status' => NotificationRecipient::STATUS_FAILED,
        ]);
    }
}
