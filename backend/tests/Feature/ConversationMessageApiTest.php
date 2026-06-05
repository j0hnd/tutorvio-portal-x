<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationAttachment;
use App\Models\ConversationMessage;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

        config([
            'chat_attachments.disk' => 'local',
            'chat_attachments.directory' => 'chat-attachments',
            'chat_attachments.max_upload_kilobytes' => 1024,
            'chat_attachments.max_files_per_message' => 5,
            'chat_attachments.max_links_per_message' => 20,
        ]);

        Storage::fake('local');
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
        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $conversation->id,
            'user_id' => $this->student->id,
            'last_read_message_id' => ConversationMessage::query()->firstOrFail()->id,
        ]);

        $this->getJson("/api/v1/conversations/{$conversation->public_id}/messages")
            ->assertOk()
            ->assertJsonPath('data.0.id', $messagePublicId)
            ->assertJsonPath('data.0.conversation_id', $conversation->public_id)
            ->assertJsonPath('data.0.sender.id', $this->student->public_id)
            ->assertJsonMissingPath('data.0.conversation_id.id');
    }

    public function test_conversation_unread_counts_ignore_sender_messages_and_mark_conversation_read(): void
    {
        $conversation = $this->createConversation([$this->student, $this->teacher]);
        $first = $this->createMessage($conversation, $this->teacher, 'First unread', now()->subMinutes(2));
        $second = $this->createMessage($conversation, $this->teacher, 'Second unread', now()->subMinute());
        $ownMessage = $this->createMessage($conversation, $this->student, 'My own message', now());

        $conversation->forceFill([
            'last_message_at' => $ownMessage->created_at,
            'last_message_by' => $this->student->id,
            'last_message_preview' => $ownMessage->body,
            'last_message_metadata' => ['message_id' => $ownMessage->public_id],
        ])->save();

        Sanctum::actingAs($this->student);

        $this->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.id', $conversation->public_id)
            ->assertJsonPath('data.0.unread_count', 2)
            ->assertJsonPath('data.0.unread_message_count', 2);

        $this->getJson('/api/v1/conversations/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 2);

        $this->postJson("/api/v1/conversations/{$conversation->public_id}/messages/read", [
            'message_id' => $first->public_id,
        ])
            ->assertOk()
            ->assertJsonPath('data.conversation_id', $conversation->public_id)
            ->assertJsonPath('data.last_read_message_id', $first->public_id)
            ->assertJsonPath('data.unread_count', 1);

        $this->getJson('/api/v1/conversations/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1);

        $this->postJson("/api/v1/conversations/{$conversation->public_id}/read")
            ->assertOk()
            ->assertJsonPath('data.conversation_id', $conversation->public_id)
            ->assertJsonPath('data.last_read_message_id', $ownMessage->public_id)
            ->assertJsonPath('data.unread_count', 0);

        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $conversation->id,
            'user_id' => $this->student->id,
            'last_read_message_id' => $ownMessage->id,
        ]);

        $this->getJson('/api/v1/conversations/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);

        $this->assertNotNull($second->public_id);
    }

    public function test_only_active_participants_can_mark_conversation_messages_read(): void
    {
        $conversation = $this->createConversation([$this->student, $this->teacher]);
        $message = $this->createMessage($conversation, $this->teacher, 'Private message', now());

        Sanctum::actingAs($this->otherStudent);

        $this->postJson("/api/v1/conversations/{$conversation->public_id}/read")
            ->assertNotFound();

        Sanctum::actingAs($this->admin);

        $this->postJson("/api/v1/conversations/{$conversation->public_id}/read")
            ->assertNotFound();

        $conversation->participants()->where('user_id', $this->student->id)->update(['archived_at' => now()]);

        Sanctum::actingAs($this->student);

        $this->postJson("/api/v1/conversations/{$conversation->public_id}/messages/read", [
            'message_id' => $message->public_id,
        ])->assertNotFound();
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

    public function test_empty_messages_are_rejected_unless_attachments_are_attached(): void
    {
        $conversation = $this->createConversation([$this->student, $this->teacher]);

        Sanctum::actingAs($this->student);

        $this->postJson("/api/v1/conversations/{$conversation->public_id}/messages", [
            'body' => '   ',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');

        $this->postJson("/api/v1/conversations/{$conversation->public_id}/messages", [
            'attachment_links' => [
                ['url' => 'https://example.test/document', 'title' => 'Shared document'],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.body', null)
            ->assertJsonPath('data.attachments.0.title', 'Shared document')
            ->assertJsonPath('data.attachments.0.url', 'https://example.test/document');
    }

    public function test_message_file_uploads_are_validated_stored_privately_and_return_safe_urls(): void
    {
        $conversation = $this->createConversation([$this->student, $this->teacher]);

        Sanctum::actingAs($this->student);

        $this->postJson("/api/v1/conversations/{$conversation->public_id}/messages", [
            'body' => 'Unsafe attachment',
            'files' => [
                UploadedFile::fake()->create('tool.exe', 64, 'application/pdf'),
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('files.0');

        $this->postJson("/api/v1/conversations/{$conversation->public_id}/messages", [
            'body' => 'Too large',
            'files' => [
                UploadedFile::fake()->create('large.pdf', 2048, 'application/pdf'),
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('files.0');

        $response = $this->postJson("/api/v1/conversations/{$conversation->public_id}/messages", [
            'body' => 'Please review the worksheet.',
            'files' => [
                UploadedFile::fake()->create('lesson"plan.pdf', 128, 'application/pdf'),
            ],
            'attachment_links' => [
                ['url' => 'https://example.test/resource', 'title' => 'Resource link'],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.attachments.0.original_filename', 'lesson_plan.pdf')
            ->assertJsonPath('data.attachments.0.mime_type', 'application/pdf')
            ->assertJsonPath('data.attachments.0.url', null)
            ->assertJsonPath('data.attachments.1.title', 'Resource link')
            ->assertJsonPath('data.attachments.1.url', 'https://example.test/resource')
            ->assertJsonMissingPath('data.attachments.0.file_path')
            ->assertJsonMissingPath('data.attachments.0.storage_disk');

        $attachment = ConversationAttachment::query()->where('type', ConversationAttachment::TYPE_FILE)->firstOrFail();

        $this->assertSame('local', $attachment->storage_disk);
        $this->assertStringStartsWith('chat-attachments/', $attachment->file_path);
        $this->assertStringNotContainsString('lesson_plan.pdf', $attachment->file_path);
        $this->assertStringNotContainsString($attachment->file_path, json_encode($response->json()));
        Storage::disk('local')->assertExists($attachment->file_path);
    }

    public function test_only_authorized_conversation_viewers_can_download_or_preview_attachments(): void
    {
        $conversation = $this->createConversation([$this->student, $this->teacher]);
        $message = $this->createMessage($conversation, $this->student, 'Attached worksheet', now());
        $attachment = $message->attachmentRecords()->create([
            'conversation_id' => $conversation->id,
            'uploaded_by' => $this->student->id,
            'type' => ConversationAttachment::TYPE_FILE,
            'storage_disk' => 'local',
            'file_path' => 'chat-attachments/private-worksheet.pdf',
            'original_filename' => 'worksheet.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 18,
        ]);

        Storage::disk('local')->put($attachment->file_path, 'private worksheet');

        $downloadUrl = "/api/v1/conversations/{$conversation->public_id}/messages/{$message->public_id}/attachments/{$attachment->public_id}/download";
        $previewUrl = "/api/v1/conversations/{$conversation->public_id}/messages/{$message->public_id}/attachments/{$attachment->public_id}/preview";

        $this->getJson($downloadUrl)->assertUnauthorized();

        Sanctum::actingAs($this->otherStudent);
        $this->getJson($downloadUrl)->assertNotFound();

        Sanctum::actingAs($this->teacher);
        $this->getJson($downloadUrl)
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertDownload('worksheet.pdf');

        $this->getJson($previewUrl)
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');

        Sanctum::actingAs($this->admin);
        $this->getJson($downloadUrl)->assertOk();
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
