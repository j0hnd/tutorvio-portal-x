<?php

namespace Tests\Feature;

use App\Models\ChatReminder;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Homework;
use App\Models\Lesson;
use App\Models\LessonNote;
use App\Models\ScheduleChangeRequest;
use App\Models\Scheduling\ClassSchedule;
use App\Models\User;
use App\Services\Messages\ChatReminderService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ChatReminderServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $student;

    private ChatReminderService $service;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = $this->userWithRole('teacher');
        $this->student = $this->userWithRole('student');
        $this->service = app(ChatReminderService::class);
    }

    public function test_supported_chat_reminders_create_system_messages_with_public_flags(): void
    {
        $conversation = $this->createConversation([$this->student, $this->teacher]);
        $lesson = $this->createLesson(['status' => Lesson::STATUS_MISSED_BY_STUDENT]);
        $schedule = $this->createClassSchedule();
        $homework = Homework::factory()->create([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'lesson_id' => $lesson->id,
            'title' => 'Unit 4 essay',
            'due_date' => '2026-06-12',
        ]);
        $lessonNote = LessonNote::query()->create([
            'lesson_id' => $lesson->id,
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'author_id' => $this->teacher->id,
            'review_status' => LessonNote::REVIEW_STATUS_PENDING,
        ]);
        $reschedule = $this->createScheduleChangeRequest();

        $this->service->upcomingLesson($conversation, $schedule);
        $this->service->homeworkDue($conversation, $homework);
        $this->service->missedClassFollowUp($conversation, $lesson);
        $this->service->pendingTeacherNote($conversation, $lessonNote);
        $this->service->rescheduleConfirmation($conversation, $reschedule);

        $this->assertDatabaseCount('chat_reminders', 5);
        $this->assertDatabaseCount('conversation_messages', 5);

        $latest = ConversationMessage::query()->latest('id')->firstOrFail();
        $this->assertNull($latest->sender_id);
        $this->assertSame(ConversationMessage::MESSAGE_TYPE_REMINDER, $latest->messageType());
        $this->assertTrue($latest->isSystemMessage());
        $this->assertTrue($latest->isReminderMessage());

        Sanctum::actingAs($this->student);

        $this->getJson("/api/v1/conversations/{$conversation->public_id}/messages?order=newest")
            ->assertOk()
            ->assertJsonPath('data.0.id', $latest->public_id)
            ->assertJsonPath('data.0.sender_id', null)
            ->assertJsonPath('data.0.message_type', ConversationMessage::MESSAGE_TYPE_REMINDER)
            ->assertJsonPath('data.0.is_system_message', true)
            ->assertJsonPath('data.0.is_reminder_message', true)
            ->assertJsonPath('data.0.reminder_type', ChatReminder::TYPE_RESCHEDULE_CONFIRMATION)
            ->assertJsonMissingPath('data.0.metadata');

        $this->getJson('/api/v1/conversations/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 5);

        $this->assertDatabaseHas('conversations', [
            'id' => $conversation->id,
            'last_message_by' => null,
        ]);
        $this->assertSame(ChatReminder::TYPE_RESCHEDULE_CONFIRMATION, $conversation->refresh()->last_message_metadata['reminder_type']);
    }

    public function test_chat_reminders_are_deduped_by_conversation_type_and_source(): void
    {
        $conversation = $this->createConversation([$this->student, $this->teacher]);
        $homework = Homework::factory()->create([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'title' => 'Practice worksheet',
        ]);

        $first = $this->service->homeworkDue($conversation, $homework);
        $second = $this->service->homeworkDue($conversation, $homework);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('chat_reminders', 1);
        $this->assertDatabaseCount('conversation_messages', 1);
    }

    public function test_chat_reminders_require_active_source_participants_in_active_conversation(): void
    {
        $conversation = $this->createConversation([$this->student]);
        $lesson = $this->createLesson();

        $this->expectException(ValidationException::class);

        $this->service->upcomingLesson($conversation, $lesson);
    }

    public function test_muted_participants_are_excluded_from_reminder_recipient_tracking(): void
    {
        $conversation = $this->createConversation([$this->student, $this->teacher]);
        $conversation->participants()
            ->where('user_id', $this->teacher->id)
            ->update(['muted_at' => now()]);
        $lesson = $this->createLesson();

        $message = $this->service->upcomingLesson($conversation, $lesson);
        $reminder = ChatReminder::query()->firstOrFail();

        $this->assertSame($message->id, $reminder->conversation_message_id);
        $this->assertSame([$this->student->id], $reminder->recipient_user_ids);
        $this->assertSame([$this->student->id], $message->metadata['recipient_user_ids']);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $user->assignRole($role);

        return $user;
    }

    /**
     * @param  array<int, User>  $participants
     */
    private function createConversation(array $participants): Conversation
    {
        $conversation = Conversation::query()->create([
            'type' => Conversation::TYPE_STUDENT_TEACHER,
            'status' => Conversation::STATUS_ACTIVE,
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'created_by' => $this->student->id,
        ]);

        foreach ($participants as $participant) {
            $roles = $participant->roles->pluck('name')->values()->all();

            $conversation->participants()->create([
                'user_id' => $participant->id,
                'participant_role' => $roles[0] ?? null,
                'participant_role_snapshot' => $roles[0] ?? null,
                'participant_roles_snapshot' => $roles,
                'joined_at' => now(),
            ]);
        }

        return $conversation;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createLesson(array $attributes = []): Lesson
    {
        return Lesson::query()->create([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'start_time' => now()->addDay()->startOfHour(),
            'end_time' => now()->addDay()->startOfHour()->addHour(),
            'status' => Lesson::STATUS_SCHEDULED,
            ...$attributes,
        ]);
    }

    private function createClassSchedule(): ClassSchedule
    {
        return ClassSchedule::query()->create([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'title' => 'Speaking class',
            'status' => ClassSchedule::STATUS_SCHEDULED,
            'timezone' => 'Asia/Manila',
            'starts_at' => now()->addDay()->startOfHour(),
            'ends_at' => now()->addDay()->startOfHour()->addHour(),
        ]);
    }

    private function createScheduleChangeRequest(): ScheduleChangeRequest
    {
        return ScheduleChangeRequest::query()->create([
            'requester_id' => $this->student->id,
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'current_starts_at' => now()->addDay(),
            'current_ends_at' => now()->addDay()->addHour(),
            'requested_starts_at' => now()->addDays(2),
            'requested_ends_at' => now()->addDays(2)->addHour(),
            'timezone' => 'Asia/Manila',
            'reason' => 'Schedule conflict',
            'status' => ScheduleChangeRequest::STATUS_APPROVED,
            'reviewed_by' => $this->teacher->id,
            'reviewed_at' => now(),
        ]);
    }
}
