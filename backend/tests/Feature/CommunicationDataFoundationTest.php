<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\AnnouncementReadState;
use App\Models\AnnouncementTarget;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\MessageThreadParticipant;
use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CommunicationDataFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_history_recipients_and_read_state_can_be_stored(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $scheduledAt = Carbon::parse('2026-06-01 09:00:00');
        $publishedAt = Carbon::parse('2026-06-01 09:05:00');
        $readAt = Carbon::parse('2026-06-01 10:00:00');

        $notification = Notification::create([
            'title' => 'Class starts soon',
            'body' => 'Your lesson starts in 30 minutes.',
            'type' => Notification::TYPE_CLASS_REMINDER,
            'sender_id' => $sender->id,
            'scheduled_at' => $scheduledAt,
            'published_at' => $publishedAt,
            'metadata' => ['lesson_id' => 123],
        ]);

        $delivery = NotificationRecipient::create([
            'notification_id' => $notification->id,
            'user_id' => $recipient->id,
            'channel' => NotificationRecipient::CHANNEL_IN_PORTAL,
            'delivery_status' => NotificationRecipient::STATUS_DELIVERED,
            'delivered_at' => $publishedAt,
            'read_at' => $readAt,
        ]);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'title' => 'Class starts soon',
            'type' => Notification::TYPE_CLASS_REMINDER,
            'sender_id' => $sender->id,
        ]);
        $this->assertDatabaseHas('notification_recipients', [
            'id' => $delivery->id,
            'notification_id' => $notification->id,
            'user_id' => $recipient->id,
            'channel' => NotificationRecipient::CHANNEL_IN_PORTAL,
            'delivery_status' => NotificationRecipient::STATUS_DELIVERED,
        ]);

        $this->assertTrue($notification->sender->is($sender));
        $this->assertTrue($notification->recipients->first()->is($delivery));
        $this->assertTrue($delivery->notification->is($notification));
        $this->assertTrue($delivery->user->is($recipient));
        $this->assertTrue($sender->sentNotifications->first()->is($notification));
        $this->assertTrue($recipient->notificationRecipients->first()->is($delivery));
        $this->assertSame(['lesson_id' => 123], $notification->metadata);
        $this->assertTrue($notification->scheduled_at->equalTo($scheduledAt));
        $this->assertTrue($delivery->read_at->equalTo($readAt));
    }

    public function test_announcement_targets_and_read_states_can_be_stored(): void
    {
        $author = User::factory()->create();
        $reader = User::factory()->create();
        $publishedAt = Carbon::parse('2026-06-02 08:00:00');
        $readAt = Carbon::parse('2026-06-02 08:15:00');

        $announcement = Announcement::create([
            'title' => 'Holiday schedule update',
            'body' => 'Classes are paused for the holiday.',
            'type' => Announcement::TYPE_ADMIN_ANNOUNCEMENT,
            'author_id' => $author->id,
            'published_at' => $publishedAt,
            'metadata' => ['priority' => 'high'],
        ]);

        $target = AnnouncementTarget::create([
            'announcement_id' => $announcement->id,
            'target_type' => AnnouncementTarget::TARGET_ROLE,
            'role' => 'student',
        ]);

        $readState = AnnouncementReadState::create([
            'announcement_id' => $announcement->id,
            'user_id' => $reader->id,
            'read_at' => $readAt,
        ]);

        $this->assertDatabaseHas('announcement_targets', [
            'id' => $target->id,
            'announcement_id' => $announcement->id,
            'target_type' => AnnouncementTarget::TARGET_ROLE,
            'role' => 'student',
        ]);
        $this->assertDatabaseHas('announcement_read_states', [
            'id' => $readState->id,
            'announcement_id' => $announcement->id,
            'user_id' => $reader->id,
        ]);

        $this->assertTrue($announcement->author->is($author));
        $this->assertTrue($announcement->targets->first()->is($target));
        $this->assertTrue($announcement->readStates->first()->is($readState));
        $this->assertTrue($target->announcement->is($announcement));
        $this->assertTrue($readState->announcement->is($announcement));
        $this->assertTrue($readState->user->is($reader));
        $this->assertTrue($author->authoredAnnouncements->first()->is($announcement));
        $this->assertTrue($reader->announcementReadStates->first()->is($readState));
        $this->assertSame(['priority' => 'high'], $announcement->metadata);
        $this->assertTrue($announcement->published_at->equalTo($publishedAt));
        $this->assertTrue($readState->read_at->equalTo($readAt));
    }

    public function test_student_teacher_message_threads_messages_and_participants_can_be_stored(): void
    {
        $student = User::factory()->create();
        $teacher = User::factory()->create();
        $sentAt = Carbon::parse('2026-06-03 15:30:00');

        $thread = MessageThread::create([
            'title' => 'Homework question',
            'thread_type' => MessageThread::TYPE_STUDENT_TEACHER,
            'status' => MessageThread::STATUS_ACTIVE,
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'created_by' => $student->id,
            'last_message_at' => $sentAt,
            'metadata' => ['homework_id' => 456],
        ]);

        $participant = MessageThreadParticipant::create([
            'message_thread_id' => $thread->id,
            'user_id' => $teacher->id,
            'participant_role' => 'teacher',
            'last_read_at' => $sentAt,
        ]);

        $message = Message::create([
            'message_thread_id' => $thread->id,
            'sender_id' => $student->id,
            'body' => 'Can you clarify exercise two?',
            'message_type' => Message::TYPE_STUDENT_TEACHER_MESSAGE,
            'sent_at' => $sentAt,
        ]);

        $this->assertDatabaseHas('message_threads', [
            'id' => $thread->id,
            'thread_type' => MessageThread::TYPE_STUDENT_TEACHER,
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
        ]);
        $this->assertDatabaseHas('message_thread_participants', [
            'id' => $participant->id,
            'message_thread_id' => $thread->id,
            'user_id' => $teacher->id,
        ]);
        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'message_thread_id' => $thread->id,
            'sender_id' => $student->id,
            'message_type' => Message::TYPE_STUDENT_TEACHER_MESSAGE,
        ]);

        $this->assertTrue($thread->student->is($student));
        $this->assertTrue($thread->teacher->is($teacher));
        $this->assertTrue($thread->createdBy->is($student));
        $this->assertTrue($thread->participants->first()->is($participant));
        $this->assertTrue($thread->messages->first()->is($message));
        $this->assertTrue($participant->thread->is($thread));
        $this->assertTrue($participant->user->is($teacher));
        $this->assertTrue($message->thread->is($thread));
        $this->assertTrue($message->sender->is($student));
        $this->assertTrue($student->studentMessageThreads->first()->is($thread));
        $this->assertTrue($teacher->teacherMessageThreads->first()->is($thread));
        $this->assertTrue($teacher->messageThreadParticipants->first()->is($participant));
        $this->assertTrue($student->sentMessages->first()->is($message));
        $this->assertSame(['homework_id' => 456], $thread->metadata);
        $this->assertTrue($message->sent_at->equalTo($sentAt));
    }

    public function test_notification_type_constants_include_supported_foundation_types(): void
    {
        $this->assertSame([
            Notification::TYPE_SYSTEM,
            Notification::TYPE_CLASS_REMINDER,
            Notification::TYPE_RESCHEDULE_ALERT,
            Notification::TYPE_HOMEWORK_REMINDER,
            Notification::TYPE_ADMIN_ANNOUNCEMENT,
            Notification::TYPE_STUDENT_TEACHER_MESSAGE,
            Notification::TYPE_EMAIL,
            Notification::TYPE_IN_PORTAL,
        ], Notification::TYPES);
    }
}
