<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ConversationMessagePinApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    private User $student;

    private User $otherStudent;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = $this->userWithRole('admin');
        $this->teacher = $this->userWithRole('teacher');
        $this->student = $this->userWithRole('student', assignedTeacher: $this->teacher);
        $this->otherStudent = $this->userWithRole('student');
    }

    public function test_teacher_can_pin_list_and_unpin_messages_in_assigned_student_conversation(): void
    {
        $conversation = $this->createConversation();
        $message = $this->createMessage($conversation, $this->student, 'Please review this before class.');

        Sanctum::actingAs($this->teacher);

        $this->postJson("/api/v1/conversations/{$conversation->public_id}/messages/{$message->public_id}/pin")
            ->assertCreated()
            ->assertJsonPath('data.conversation_id', $conversation->public_id)
            ->assertJsonPath('data.message_id', $message->public_id)
            ->assertJsonPath('data.pinned_by', $this->teacher->public_id)
            ->assertJsonPath('data.message.body', 'Please review this before class.');

        $this->getJson("/api/v1/conversations/{$conversation->public_id}/pinned-messages")
            ->assertOk()
            ->assertJsonPath('data.0.message_id', $message->public_id)
            ->assertJsonPath('data.0.message.sender_id', $this->student->public_id);

        $this->getJson("/api/v1/conversations/{$conversation->public_id}")
            ->assertOk()
            ->assertJsonPath('data.pinned_messages_summary.count', 1)
            ->assertJsonPath('data.pinned_messages_summary.latest.message_id', $message->public_id)
            ->assertJsonPath('data.pinned_messages_summary.latest.body_preview', 'Please review this before class.')
            ->assertJsonPath('data.permission_metadata.can_pin_messages', true);

        $this->deleteJson("/api/v1/conversations/{$conversation->public_id}/messages/{$message->public_id}/pin")
            ->assertOk()
            ->assertJsonPath('data.is_pinned', false);

        $this->assertDatabaseMissing('conversation_message_pins', [
            'conversation_message_id' => $message->id,
        ]);
    }

    public function test_admin_can_pin_messages_where_conversation_policy_allows(): void
    {
        $conversation = $this->createConversation();
        $message = $this->createMessage($conversation, $this->student);

        Sanctum::actingAs($this->admin);

        $this->postJson("/api/v1/conversations/{$conversation->public_id}/messages/{$message->public_id}/pin")
            ->assertCreated()
            ->assertJsonPath('data.pinned_by', $this->admin->public_id);
    }

    public function test_student_pin_access_is_restricted_by_default_and_configurable(): void
    {
        $conversation = $this->createConversation();
        $message = $this->createMessage($conversation, $this->teacher);

        Sanctum::actingAs($this->student);

        $this->postJson("/api/v1/conversations/{$conversation->public_id}/messages/{$message->public_id}/pin")
            ->assertForbidden();

        config(['chat.allow_student_message_pins' => true]);

        $this->postJson("/api/v1/conversations/{$conversation->public_id}/messages/{$message->public_id}/pin")
            ->assertCreated()
            ->assertJsonPath('data.pinned_by', $this->student->public_id);
    }

    public function test_users_cannot_pin_messages_from_conversations_they_cannot_access(): void
    {
        $conversation = $this->createConversation();
        $message = $this->createMessage($conversation, $this->student);

        Sanctum::actingAs($this->otherStudent);

        $this->postJson("/api/v1/conversations/{$conversation->public_id}/messages/{$message->public_id}/pin")
            ->assertNotFound();
    }

    public function test_pinned_messages_are_only_visible_inside_allowed_conversations(): void
    {
        $conversation = $this->createConversation();
        $message = $this->createMessage($conversation, $this->student);

        Sanctum::actingAs($this->teacher);

        $this->postJson("/api/v1/conversations/{$conversation->public_id}/messages/{$message->public_id}/pin")
            ->assertCreated();

        Sanctum::actingAs($this->otherStudent);

        $this->getJson("/api/v1/conversations/{$conversation->public_id}/pinned-messages")
            ->assertNotFound();
    }

    public function test_message_must_belong_to_the_conversation_being_pinned(): void
    {
        $conversation = $this->createConversation();
        $otherConversation = $this->createConversation();
        $message = $this->createMessage($otherConversation, $this->student);

        Sanctum::actingAs($this->teacher);

        $this->postJson("/api/v1/conversations/{$conversation->public_id}/messages/{$message->public_id}/pin")
            ->assertNotFound();
    }

    public function test_deleted_messages_are_not_returned_as_pinned_messages(): void
    {
        $conversation = $this->createConversation();
        $message = $this->createMessage($conversation, $this->student);

        Sanctum::actingAs($this->teacher);

        $this->postJson("/api/v1/conversations/{$conversation->public_id}/messages/{$message->public_id}/pin")
            ->assertCreated();

        $message->delete();

        $this->getJson("/api/v1/conversations/{$conversation->public_id}/pinned-messages")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson("/api/v1/conversations/{$conversation->public_id}")
            ->assertOk()
            ->assertJsonPath('data.pinned_messages_summary.count', 0)
            ->assertJsonPath('data.pinned_messages_summary.latest', null);

        $this->assertDatabaseMissing('conversation_message_pins', [
            'conversation_message_id' => $message->id,
        ]);
    }

    private function userWithRole(string $role, ?User $assignedTeacher = null): User
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $user->assignRole($role);

        if ($role === 'student') {
            $user->studentProfile()->create([
                'assigned_teacher_id' => $assignedTeacher?->id,
            ]);
        }

        return $user;
    }

    private function createConversation(): Conversation
    {
        $conversation = Conversation::query()->create([
            'type' => Conversation::TYPE_STUDENT_TEACHER,
            'status' => Conversation::STATUS_ACTIVE,
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'created_by' => $this->student->id,
        ]);

        foreach ([$this->student, $this->teacher] as $participant) {
            $role = $participant->roles->pluck('name')->first();

            $conversation->participants()->create([
                'user_id' => $participant->id,
                'participant_role' => $role,
                'participant_role_snapshot' => $role,
                'participant_roles_snapshot' => [$role],
                'joined_at' => now(),
            ]);
        }

        return $conversation;
    }

    private function createMessage(Conversation $conversation, User $sender, string $body = 'Pinned message body.'): ConversationMessage
    {
        return ConversationMessage::query()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'body' => $body,
            'status' => ConversationMessage::STATUS_SENT,
        ]);
    }
}
