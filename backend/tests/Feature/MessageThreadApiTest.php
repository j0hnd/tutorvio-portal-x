<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\MessageThread;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MessageThreadApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    private User $otherTeacher;

    private User $student;

    private User $otherStudent;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->admin->assignRole('admin');

        $this->teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->teacher->assignRole('teacher');

        $this->otherTeacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->otherTeacher->assignRole('teacher');

        $this->student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->student->assignRole('student');
        $this->student->studentProfile()->create([
            'assigned_teacher_id' => $this->teacher->id,
        ]);

        $this->otherStudent = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->otherStudent->assignRole('student');
        $this->otherStudent->studentProfile()->create([
            'assigned_teacher_id' => $this->otherTeacher->id,
        ]);
    }

    public function test_student_can_create_thread_with_assigned_teacher_and_send_messages(): void
    {
        Sanctum::actingAs($this->student);

        $response = $this->postJson('/api/v1/message-threads', [
            'recipient_id' => $this->teacher->id,
            'title' => 'Homework question',
            'body' => 'Can you clarify exercise two?',
        ])
            ->assertCreated()
            ->assertJsonPath('data.student_id', $this->student->public_id)
            ->assertJsonPath('data.teacher_id', $this->teacher->public_id)
            ->assertJsonPath('data.unread_count', 0)
            ->assertJsonPath('data.latest_message.body', 'Can you clarify exercise two?');

        $threadPublicId = $response->json('data.id');
        $threadId = MessageThread::query()
            ->where('public_id', $threadPublicId)
            ->firstOrFail()
            ->id;

        $this->assertDatabaseHas('message_thread_participants', [
            'message_thread_id' => $threadId,
            'user_id' => $this->student->id,
            'participant_role' => 'student',
        ]);
        $this->assertDatabaseHas('message_thread_participants', [
            'message_thread_id' => $threadId,
            'user_id' => $this->teacher->id,
            'participant_role' => 'teacher',
        ]);
        $this->assertDatabaseHas('messages', [
            'message_thread_id' => $threadId,
            'sender_id' => $this->student->id,
            'body' => 'Can you clarify exercise two?',
        ]);

        $this->postJson("/api/v1/message-threads/{$threadId}/messages", [
            'body' => 'Numeric thread IDs are no longer accepted.',
        ])
            ->assertNotFound();

        $this->postJson("/api/v1/message-threads/{$threadPublicId}/messages", [
            'body' => 'I can send more context if helpful.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.sender_id', $this->student->public_id);
    }

    public function test_student_cannot_create_thread_with_unassigned_teacher(): void
    {
        Sanctum::actingAs($this->student);

        $this->postJson('/api/v1/message-threads', [
            'recipient_id' => $this->otherTeacher->id,
            'body' => 'Can I ask a question?',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('recipient_id');
    }

    public function test_teacher_can_only_message_assigned_students(): void
    {
        Sanctum::actingAs($this->teacher);

        $this->postJson('/api/v1/message-threads', [
            'recipient_id' => $this->student->id,
            'body' => 'Please review your homework feedback.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.student_id', $this->student->public_id)
            ->assertJsonPath('data.teacher_id', $this->teacher->public_id);

        $this->postJson('/api/v1/message-threads', [
            'recipient_id' => $this->otherStudent->id,
            'body' => 'This should not be allowed.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('recipient_id');
    }

    public function test_participants_can_view_thread_messages_and_mark_unread_messages_read(): void
    {
        $thread = $this->createThread();

        Message::create([
            'message_thread_id' => $thread->id,
            'sender_id' => $this->student->id,
            'body' => 'Can you check this?',
            'message_type' => Message::TYPE_STUDENT_TEACHER_MESSAGE,
            'sent_at' => now(),
        ]);

        Sanctum::actingAs($this->teacher);

        $this->getJson('/api/v1/message-threads')
            ->assertOk()
            ->assertJsonPath('data.0.id', $thread->public_id)
            ->assertJsonPath('data.0.unread_count', 1);

        $this->getJson('/api/v1/message-threads/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1);

        $this->getJson("/api/v1/message-threads/{$thread->public_id}/messages")
            ->assertOk()
            ->assertJsonPath('data.0.body', 'Can you check this?')
            ->assertJsonMissingPath('data.0.id');

        $this->postJson("/api/v1/message-threads/{$thread->public_id}/read")
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);

        $this->getJson('/api/v1/message-threads/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);
    }

    public function test_users_cannot_view_threads_they_do_not_belong_to(): void
    {
        $thread = $this->createThread();

        Sanctum::actingAs($this->otherStudent);

        $this->getJson("/api/v1/message-threads/{$thread->public_id}/messages")
            ->assertNotFound();

        $this->postJson("/api/v1/message-threads/{$thread->public_id}/messages", [
            'body' => 'Trying to join.',
        ])
            ->assertNotFound();
    }

    public function test_admin_can_view_and_create_student_teacher_threads(): void
    {
        $thread = $this->createThread();

        Sanctum::actingAs($this->admin);

        $this->getJson("/api/v1/message-threads/{$thread->public_id}/messages")
            ->assertOk();

        $this->postJson('/api/v1/message-threads', [
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'title' => 'Admin-created support thread',
        ])
            ->assertCreated()
            ->assertJsonPath('data.created_by', $this->admin->id);
    }

    public function test_staff_message_access_depends_on_permission(): void
    {
        $thread = $this->createThread();
        $staff = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('staff');

        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/message-threads')
            ->assertForbidden();

        $this->getJson("/api/v1/message-threads/{$thread->public_id}/messages")
            ->assertForbidden();

        $staff->givePermissionTo('messages.view');

        $this->getJson('/api/v1/message-threads')
            ->assertOk()
            ->assertJsonPath('data.0.id', $thread->public_id);

        $this->postJson('/api/v1/message-threads', [
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'title' => 'Staff-created thread without manage permission',
        ])
            ->assertForbidden();

        $staff->givePermissionTo('messages.manage');

        $this->postJson('/api/v1/message-threads', [
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'title' => 'Staff-created support thread',
        ])
            ->assertCreated()
            ->assertJsonPath('data.created_by', $staff->id);
    }

    private function createThread(): MessageThread
    {
        $thread = MessageThread::create([
            'title' => 'Homework question',
            'thread_type' => MessageThread::TYPE_STUDENT_TEACHER,
            'status' => MessageThread::STATUS_ACTIVE,
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'created_by' => $this->student->id,
        ]);

        $thread->participants()->create([
            'user_id' => $this->student->id,
            'participant_role' => 'student',
            'last_read_at' => now(),
        ]);
        $thread->participants()->create([
            'user_id' => $this->teacher->id,
            'participant_role' => 'teacher',
        ]);

        return $thread;
    }
}
