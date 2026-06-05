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

class ConversationMessageApiTest extends TestCase
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
        $this->student = $this->userWithRole('student');
        $this->otherStudent = $this->userWithRole('student');
    }

    public function test_active_participant_can_send_and_read_conversation_messages(): void
    {
        $conversation = $this->createConversation([$this->student, $this->teacher]);

        Sanctum::actingAs($this->student);

        $response = $this->postJson("/api/v1/conversations/{$conversation->public_id}/messages", [
            'body' => 'Please review this practice answer.',
            'links' => ['https://example.test/practice-answer'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.conversation_id', $conversation->public_id)
            ->assertJsonPath('data.sender_id', $this->student->public_id)
            ->assertJsonPath('data.body', 'Please review this practice answer.')
            ->assertJsonPath('data.links.0', 'https://example.test/practice-answer')
            ->assertJsonPath('data.status', ConversationMessage::STATUS_SENT)
            ->assertJsonPath('data.deleted_at', null)
            ->assertJsonMissingPath('data.metadata');

        $messagePublicId = $response->json('data.id');
        $this->assertNotNull($messagePublicId);
        $this->assertNotSame((string) ConversationMessage::query()->firstOrFail()->id, $messagePublicId);

        $this->assertDatabaseHas('conversations', [
            'id' => $conversation->id,
            'last_message_by' => $this->student->id,
            'last_message_preview' => 'Please review this practice answer.',
        ]);

        $this->getJson("/api/v1/conversations/{$conversation->public_id}/messages")
            ->assertOk()
            ->assertJsonPath('data.0.id', $messagePublicId)
            ->assertJsonPath('data.0.conversation_id', $conversation->public_id)
            ->assertJsonPath('data.0.sender.id', $this->student->public_id)
            ->assertJsonMissingPath('data.0.conversation_id.id');
    }

    public function test_message_history_supports_pagination_and_newest_ordering(): void
    {
        $conversation = $this->createConversation([$this->student, $this->teacher]);
        $oldest = $this->createMessage($conversation, $this->student, 'Oldest message', now()->subMinutes(2));
        $newest = $this->createMessage($conversation, $this->teacher, 'Newest message', now());

        Sanctum::actingAs($this->student);

        $this->getJson("/api/v1/conversations/{$conversation->public_id}/messages?order=newest&per_page=1")
            ->assertOk()
            ->assertJsonPath('data.0.id', $newest->public_id)
            ->assertJsonPath('per_page', 1)
            ->assertJsonPath('total', 2);

        $this->getJson("/api/v1/conversations/{$conversation->public_id}/messages?order=oldest")
            ->assertOk()
            ->assertJsonPath('data.0.id', $oldest->public_id);
    }

    public function test_empty_messages_are_rejected_unless_files_are_attached(): void
    {
        $conversation = $this->createConversation([$this->student, $this->teacher]);

        Sanctum::actingAs($this->student);

        $this->postJson("/api/v1/conversations/{$conversation->public_id}/messages", [
            'body' => '   ',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');

        $this->postJson("/api/v1/conversations/{$conversation->public_id}/messages", [
            'attachments' => [
                ['id' => 'file_01HX0000000000000000000000', 'name' => 'worksheet.pdf'],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.body', null)
            ->assertJsonPath('data.attachments.0.name', 'worksheet.pdf');
    }

    public function test_send_requires_active_participant_active_conversation_and_message_permission(): void
    {
        $conversation = $this->createConversation([$this->student, $this->teacher]);

        Sanctum::actingAs($this->otherStudent);

        $this->postJson("/api/v1/conversations/{$conversation->public_id}/messages", [
            'body' => 'I should not reach this conversation.',
        ])->assertNotFound();

        Sanctum::actingAs($this->admin);

        $this->postJson("/api/v1/conversations/{$conversation->public_id}/messages", [
            'body' => 'Admin is not a sender participant here.',
        ])->assertForbidden();

        $conversation->participants()->where('user_id', $this->student->id)->update(['archived_at' => now()]);
        Sanctum::actingAs($this->student);

        $this->postJson("/api/v1/conversations/{$conversation->public_id}/messages", [
            'body' => 'Archived participants cannot send.',
        ])->assertNotFound();

        $conversation = $this->createConversation([$this->student, $this->teacher], [
            'status' => Conversation::STATUS_CLOSED,
        ]);

        $this->postJson("/api/v1/conversations/{$conversation->public_id}/messages", [
            'body' => 'Closed conversations reject messages.',
        ])->assertForbidden();

        $inactiveStudent = $this->userWithRole('student', User::STATUS_INACTIVE);
        $conversation = $this->createConversation([$inactiveStudent, $this->teacher]);
        Sanctum::actingAs($inactiveStudent);

        $this->postJson("/api/v1/conversations/{$conversation->public_id}/messages", [
            'body' => 'Inactive users cannot send.',
        ])->assertForbidden();
    }

    private function userWithRole(string $role, string $status = User::STATUS_ACTIVE): User
    {
        $user = User::factory()->create(['status' => $status]);
        $user->assignRole($role);

        return $user;
    }

    /**
     * @param  array<int, User>  $participants
     * @param  array<string, mixed>  $attributes
     */
    private function createConversation(array $participants, array $attributes = []): Conversation
    {
        $conversation = Conversation::query()->create(array_merge([
            'type' => Conversation::TYPE_STUDENT_TEACHER,
            'status' => Conversation::STATUS_ACTIVE,
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'created_by' => $this->student->id,
        ], $attributes));

        foreach ($participants as $participant) {
            $role = $participant->roles->pluck('name')->first();

            $conversation->participants()->create([
                'user_id' => $participant->id,
                'participant_role' => $role,
                'participant_role_snapshot' => $role,
                'participant_roles_snapshot' => $participant->roles->pluck('name')->values()->all(),
                'joined_at' => now(),
            ]);
        }

        return $conversation;
    }

    private function createMessage(Conversation $conversation, User $sender, string $body, mixed $createdAt): ConversationMessage
    {
        return ConversationMessage::query()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'body' => $body,
            'status' => ConversationMessage::STATUS_SENT,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
