<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-15 12:00:00');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->otherUser = User::factory()->create(['status' => User::STATUS_ACTIVE]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_user_can_list_only_their_own_notifications(): void
    {
        Sanctum::actingAs($this->user);

        $notification = $this->createNotificationFor($this->user, [
            'title' => 'Class starts soon',
            'body' => 'Your lesson starts in 30 minutes.',
            'type' => Notification::TYPE_CLASS_REMINDER,
            'metadata' => ['lesson_id' => 123],
            'published_at' => '2026-06-15 09:00:00',
        ]);
        $this->createNotificationFor($this->otherUser, [
            'title' => 'Other user notification',
            'published_at' => '2026-06-15 10:00:00',
        ]);

        $this->getJson('/api/v1/notifications?per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $notification->public_id)
            ->assertJsonPath('data.0.title', 'Class starts soon')
            ->assertJsonPath('data.0.body', 'Your lesson starts in 30 minutes.')
            ->assertJsonPath('data.0.message', 'Your lesson starts in 30 minutes.')
            ->assertJsonPath('data.0.type', Notification::TYPE_CLASS_REMINDER)
            ->assertJsonPath('data.0.is_read', false)
            ->assertJsonPath('data.0.read_status', 'unread')
            ->assertJsonPath('data.0.metadata.lesson_id', 123);
    }

    public function test_user_can_filter_notifications_by_read_status_type_and_date_range(): void
    {
        Sanctum::actingAs($this->user);

        $readClassReminder = $this->createNotificationFor($this->user, [
            'title' => 'Read class reminder',
            'type' => Notification::TYPE_CLASS_REMINDER,
            'published_at' => '2026-06-10 09:00:00',
            'created_at' => '2026-06-10 09:00:00',
        ], [
            'read_at' => '2026-06-10 09:05:00',
        ]);
        $this->createNotificationFor($this->user, [
            'title' => 'Unread homework reminder',
            'type' => Notification::TYPE_HOMEWORK_REMINDER,
            'published_at' => '2026-06-12 09:00:00',
            'created_at' => '2026-06-12 09:00:00',
        ]);
        $unreadClassReminder = $this->createNotificationFor($this->user, [
            'title' => 'Unread class reminder',
            'type' => Notification::TYPE_CLASS_REMINDER,
            'published_at' => '2026-06-13 09:00:00',
            'created_at' => '2026-06-13 09:00:00',
        ]);

        $this->getJson('/api/v1/notifications?status=read&per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $readClassReminder->public_id)
            ->assertJsonPath('data.0.read_status', 'read');

        $this->getJson('/api/v1/notifications?unread=true&type='.Notification::TYPE_CLASS_REMINDER.'&date_from=2026-06-11&date_to=2026-06-14&per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $unreadClassReminder->public_id);
    }

    public function test_user_can_view_and_mark_only_their_own_notification_as_read(): void
    {
        Sanctum::actingAs($this->user);

        $notification = $this->createNotificationFor($this->user);
        $otherNotification = $this->createNotificationFor($this->otherUser);

        $this->getJson("/api/v1/notifications/{$notification->public_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $notification->public_id)
            ->assertJsonPath('data.read_status', 'unread');

        $this->getJson("/api/v1/notifications/{$notification->id}")
            ->assertNotFound();

        $this->getJson("/api/v1/notifications/{$otherNotification->id}")
            ->assertNotFound();

        $this->postJson("/api/v1/notifications/{$otherNotification->id}/read")
            ->assertNotFound();

        $this->postJson("/api/v1/notifications/{$notification->public_id}/read")
            ->assertOk()
            ->assertJsonPath('data.id', $notification->public_id)
            ->assertJsonPath('data.is_read', true)
            ->assertJsonPath('data.read_status', 'read');

        $this->assertDatabaseHas('notification_recipients', [
            'notification_id' => $notification->id,
            'user_id' => $this->user->id,
            'read_at' => Carbon::now(),
        ]);
        $this->assertDatabaseHas('notification_recipients', [
            'notification_id' => $otherNotification->id,
            'user_id' => $this->otherUser->id,
            'read_at' => null,
        ]);
    }

    public function test_user_can_get_unread_count_and_mark_all_notifications_as_read(): void
    {
        Sanctum::actingAs($this->user);

        $this->createNotificationFor($this->user);
        $this->createNotificationFor($this->user);
        $this->createNotificationFor($this->user, [], [
            'read_at' => '2026-06-15 10:00:00',
        ]);
        $this->createNotificationFor($this->otherUser);

        $this->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 2);

        $this->postJson('/api/v1/notifications/mark-all-read')
            ->assertOk()
            ->assertJsonPath('data.marked_read_count', 2);

        $this->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);
    }

    public function test_notification_history_visibility_is_limited_by_role_and_permission(): void
    {
        $admin = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $admin->assignRole('admin');
        $staff = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('staff');
        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $teacher->assignRole('teacher');
        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole('student');

        $this->createNotificationFor($this->user, [
            'title' => 'Older notification',
            'published_at' => '2026-06-13 09:00:00',
            'created_at' => '2026-06-13 09:00:00',
        ]);
        $staffNotification = $this->createNotificationFor($staff, [
            'title' => 'Staff notification',
            'published_at' => '2026-06-14 09:00:00',
            'created_at' => '2026-06-14 09:00:00',
        ]);
        $other = $this->createNotificationFor($this->otherUser, [
            'title' => 'Other user notification',
            'published_at' => '2026-06-15 09:00:00',
            'created_at' => '2026-06-15 09:00:00',
        ]);

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/notifications/history?per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $other->public_id)
            ->assertJsonPath('data.0.recipient_user_id', $this->otherUser->public_id)
            ->assertJsonPath('per_page', 1)
            ->assertJsonPath('total', 3);

        Sanctum::actingAs($staff);
        $this->getJson('/api/v1/notifications/history?per_page=10')
            ->assertForbidden();

        $staff->givePermissionTo('notifications.history.view');

        $this->getJson('/api/v1/notifications/history?per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $staffNotification->public_id)
            ->assertJsonPath('data.0.recipient_user_id', $staff->public_id)
            ->assertJsonPath('total', 1);

        Sanctum::actingAs($teacher);
        $this->getJson('/api/v1/notifications/history?per_page=10')
            ->assertForbidden();

        Sanctum::actingAs($student);
        $this->getJson('/api/v1/notifications/history?per_page=10')
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $notificationAttributes
     * @param  array<string, mixed>  $recipientAttributes
     */
    private function createNotificationFor(
        User $user,
        array $notificationAttributes = [],
        array $recipientAttributes = []
    ): Notification {
        $createdAt = $notificationAttributes['created_at'] ?? null;
        unset($notificationAttributes['created_at']);

        $notification = Notification::create(array_merge([
            'title' => 'Portal notification',
            'body' => 'A new portal notification is available.',
            'type' => Notification::TYPE_SYSTEM,
            'published_at' => '2026-06-15 09:00:00',
        ], $notificationAttributes));

        if ($createdAt !== null) {
            $notification->forceFill([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->save();
        }

        NotificationRecipient::create(array_merge([
            'notification_id' => $notification->id,
            'user_id' => $user->id,
            'channel' => NotificationRecipient::CHANNEL_IN_PORTAL,
            'delivery_status' => NotificationRecipient::STATUS_DELIVERED,
            'delivered_at' => $notification->published_at,
        ], $recipientAttributes));

        return $notification;
    }
}
