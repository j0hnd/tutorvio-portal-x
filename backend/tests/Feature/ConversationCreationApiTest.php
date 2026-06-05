<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\CourseProgram;
use App\Models\CourseProgramStudentAssignment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ConversationCreationApiTest extends TestCase
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

        $this->admin = $this->userWithRole('admin');
        $this->teacher = $this->userWithRole('teacher');
        $this->otherTeacher = $this->userWithRole('teacher');
        $this->student = $this->userWithRole('student', assignedTeacher: $this->teacher);
        $this->otherStudent = $this->userWithRole('student', assignedTeacher: $this->otherTeacher);
    }

    public function test_student_can_start_chat_with_assigned_teacher_and_allowed_admin_contact(): void
    {
        $staffContact = $this->userWithRole('staff');
        $staffContact->givePermissionTo('messages.manage');

        Sanctum::actingAs($this->student);

        $this->postJson('/api/v1/conversations', [
            'recipient_id' => $this->teacher->public_id,
            'title' => 'Class question',
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', Conversation::TYPE_STUDENT_TEACHER)
            ->assertJsonPath('data.student_id', $this->student->public_id)
            ->assertJsonPath('data.teacher_id', $this->teacher->public_id)
            ->assertJsonFragment(['user_id' => $this->student->public_id])
            ->assertJsonMissingPath('data.created_by');

        $this->postJson('/api/v1/conversations', [
            'recipient_id' => $staffContact->public_id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', Conversation::TYPE_ADMIN_STUDENT)
            ->assertJsonPath('data.student_id', $this->student->public_id);

        $this->postJson('/api/v1/conversations', [
            'recipient_id' => $this->otherTeacher->public_id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('recipient_id');
    }

    public function test_student_cannot_start_chat_with_staff_without_message_manage_permission(): void
    {
        $staff = $this->userWithRole('staff');
        $staff->givePermissionTo('messages.view');

        Sanctum::actingAs($this->student);

        $this->postJson('/api/v1/conversations', [
            'recipient_id' => $staff->public_id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('recipient_id');
    }

    public function test_teacher_can_start_chat_with_assigned_student_and_admin_contact_only(): void
    {
        Sanctum::actingAs($this->teacher);

        $this->postJson('/api/v1/conversations', [
            'recipient_id' => $this->student->public_id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', Conversation::TYPE_STUDENT_TEACHER)
            ->assertJsonPath('data.student_id', $this->student->public_id)
            ->assertJsonPath('data.teacher_id', $this->teacher->public_id);

        $this->postJson('/api/v1/conversations', [
            'recipient_id' => $this->admin->public_id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', Conversation::TYPE_TEACHER_ADMIN)
            ->assertJsonPath('data.teacher_id', $this->teacher->public_id);

        $this->postJson('/api/v1/conversations', [
            'recipient_id' => $this->otherStudent->public_id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('recipient_id');
    }

    public function test_admin_can_create_student_teacher_and_course_group_conversations(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/conversations', [
            'recipient_id' => $this->student->public_id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', Conversation::TYPE_ADMIN_STUDENT)
            ->assertJsonPath('data.created_by', $this->admin->public_id);

        $this->postJson('/api/v1/conversations', [
            'recipient_id' => $this->teacher->public_id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', Conversation::TYPE_TEACHER_ADMIN)
            ->assertJsonPath('data.teacher_id', $this->teacher->public_id);

        $courseProgram = CourseProgram::factory()->create(['created_by' => $this->admin->id]);
        CourseProgramStudentAssignment::query()->create([
            'course_program_id' => $courseProgram->id,
            'student_id' => $this->student->id,
            'assigned_by' => $this->admin->id,
            'assigned_at' => now(),
            'status' => CourseProgramStudentAssignment::STATUS_ACTIVE,
        ]);

        $response = $this->postJson('/api/v1/conversations', [
            'type' => Conversation::TYPE_GROUP_COURSE,
            'course_program_id' => $courseProgram->public_id,
            'participant_ids' => [$this->teacher->public_id],
            'title' => 'B1 discussion',
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', Conversation::TYPE_GROUP_COURSE)
            ->assertJsonPath('data.course_program_id', $courseProgram->public_id);

        $conversation = Conversation::query()
            ->where('public_id', $response->json('data.id'))
            ->firstOrFail();

        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $conversation->id,
            'user_id' => $this->student->id,
            'participant_role' => 'student',
        ]);
        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $conversation->id,
            'user_id' => $this->teacher->id,
            'participant_role' => 'teacher',
        ]);
        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $conversation->id,
            'user_id' => $this->admin->id,
            'participant_role' => 'admin',
        ]);
    }

    public function test_staff_creation_access_depends_on_message_manage_permission(): void
    {
        $staff = $this->userWithRole('staff');

        Sanctum::actingAs($staff);

        $this->postJson('/api/v1/conversations', [
            'student_id' => $this->student->public_id,
            'admin_id' => $this->admin->public_id,
        ])
            ->assertForbidden();

        $staff->givePermissionTo('messages.view');

        $this->postJson('/api/v1/conversations', [
            'student_id' => $this->student->public_id,
            'admin_id' => $this->admin->public_id,
        ])
            ->assertForbidden();

        $staff->givePermissionTo('messages.manage');

        $this->postJson('/api/v1/conversations', [
            'student_id' => $this->student->public_id,
            'admin_id' => $staff->public_id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', Conversation::TYPE_ADMIN_STUDENT)
            ->assertJsonPath('data.created_by', $staff->public_id);
    }

    public function test_active_one_to_one_conversation_is_not_duplicated(): void
    {
        Sanctum::actingAs($this->student);

        $first = $this->postJson('/api/v1/conversations', [
            'recipient_id' => $this->teacher->public_id,
        ])
            ->assertCreated()
            ->json('data.id');

        $this->postJson('/api/v1/conversations', [
            'recipient_id' => $this->teacher->public_id,
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $first);

        $this->assertSame(1, Conversation::query()->where('type', Conversation::TYPE_STUDENT_TEACHER)->count());
    }

    public function test_participants_can_view_their_conversation_list_and_detail(): void
    {
        $sentAt = now();
        $conversation = $this->createConversation([$this->student, $this->teacher], [
            'last_message_by' => $this->teacher->id,
            'last_message_at' => $sentAt,
            'last_message_preview' => 'Please review the practice notes.',
            'last_message_metadata' => ['message_type' => 'text'],
            'metadata' => ['topic' => 'homework'],
        ]);
        ConversationMessage::query()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $this->teacher->id,
            'body' => 'Please review the practice notes.',
            'status' => ConversationMessage::STATUS_SENT,
            'created_at' => $sentAt,
            'updated_at' => $sentAt,
        ]);

        Sanctum::actingAs($this->student);

        $this->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.id', $conversation->public_id)
            ->assertJsonPath('data.0.type', Conversation::TYPE_STUDENT_TEACHER)
            ->assertJsonPath('data.0.display_title', $this->teacher->name)
            ->assertJsonPath('data.0.last_message_preview', 'Please review the practice notes.')
            ->assertJsonPath('data.0.last_message_metadata.message_type', 'text')
            ->assertJsonPath('data.0.metadata.topic', 'homework')
            ->assertJsonPath('data.0.unread_message_count', 1)
            ->assertJsonPath('data.0.participant_summary.total', 2)
            ->assertJsonPath('data.0.participant_summary.preview.0.user_id', $this->teacher->public_id)
            ->assertJsonPath('data.0.is_pinned', true)
            ->assertJsonPath('data.0.is_archived', false)
            ->assertJsonPath('data.0.is_closed', false)
            ->assertJsonPath('data.0.permission_metadata.can_send_messages', true)
            ->assertJsonPath('data.0.permission_metadata.can_upload_files', true)
            ->assertJsonPath('data.0.permission_metadata.can_pin_messages', false)
            ->assertJsonPath('data.0.permission_metadata.can_close_conversation', false)
            ->assertJsonPath('data.0.permission_metadata.can_archive_conversation', false)
            ->assertJsonMissingPath('data.0.created_by');

        $this->getJson("/api/v1/conversations/{$conversation->public_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $conversation->public_id)
            ->assertJsonPath('data.student_id', $this->student->public_id)
            ->assertJsonPath('data.teacher_id', $this->teacher->public_id)
            ->assertJsonPath('data.last_message_by', $this->teacher->public_id)
            ->assertJsonFragment(['user_id' => $this->student->public_id])
            ->assertJsonPath('data.permission_metadata.current_user_id', $this->student->public_id)
            ->assertJsonPath('data.permission_metadata.is_participant', true);

        $this->getJson("/api/v1/conversations/{$conversation->id}")
            ->assertNotFound();
    }

    public function test_users_cannot_view_conversations_where_they_are_not_participants(): void
    {
        $conversation = $this->createConversation([$this->student, $this->teacher]);

        Sanctum::actingAs($this->otherStudent);

        $this->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson("/api/v1/conversations/{$conversation->public_id}")
            ->assertNotFound();
    }

    public function test_staff_conversation_visibility_depends_on_message_permission(): void
    {
        $conversation = $this->createConversation([$this->student, $this->teacher]);
        $staff = $this->userWithRole('staff');

        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/conversations')
            ->assertForbidden();

        $this->getJson("/api/v1/conversations/{$conversation->public_id}")
            ->assertForbidden();

        $staff->givePermissionTo('messages.view');

        $this->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.id', $conversation->public_id);

        $this->getJson("/api/v1/conversations/{$conversation->public_id}")
            ->assertOk()
            ->assertJsonPath('data.permission_metadata.is_participant', false)
            ->assertJsonPath('data.permission_metadata.can_send_messages', false)
            ->assertJsonPath('data.permission_metadata.can_upload_files', false)
            ->assertJsonPath('data.permission_metadata.can_pin_messages', false)
            ->assertJsonPath('data.permission_metadata.can_close_conversation', false)
            ->assertJsonPath('data.permission_metadata.can_archive_conversation', false);
    }

    public function test_admin_policy_allows_viewing_and_managing_conversation_details(): void
    {
        $conversation = $this->createConversation([$this->student, $this->teacher], [
            'created_by' => $this->student->id,
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson("/api/v1/conversations/{$conversation->public_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $conversation->public_id)
            ->assertJsonPath('data.created_by', $this->student->public_id)
            ->assertJsonPath('data.permission_metadata.is_participant', false)
            ->assertJsonPath('data.permission_metadata.can_send_messages', true)
            ->assertJsonPath('data.permission_metadata.can_upload_files', true)
            ->assertJsonPath('data.permission_metadata.can_pin_messages', true)
            ->assertJsonPath('data.permission_metadata.can_close_conversation', true)
            ->assertJsonPath('data.permission_metadata.can_archive_conversation', true);
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

    /**
     * @param  array<int, User>  $participants
     * @param  array<string, mixed>  $attributes
     */
    private function createConversation(array $participants, array $attributes = []): Conversation
    {
        $conversation = Conversation::query()->create(array_merge([
            'type' => Conversation::TYPE_STUDENT_TEACHER,
            'title' => null,
            'status' => Conversation::STATUS_ACTIVE,
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'created_by' => $this->student->id,
        ], $attributes));

        foreach ($participants as $participant) {
            $roles = $participant->roles->pluck('name')->values()->all();
            $role = $roles[0] ?? null;

            $conversation->participants()->create([
                'user_id' => $participant->id,
                'participant_role' => $role,
                'participant_role_snapshot' => $role,
                'participant_roles_snapshot' => $roles,
                'joined_at' => now(),
                'last_read_at' => $participant->is($this->student) ? now()->subMinute() : null,
                'metadata' => $participant->is($this->student) ? ['is_pinned' => true] : null,
            ]);
        }

        return $conversation;
    }
}
