<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationEscalation;
use App\Models\ConversationMessage;
use App\Models\IssueReport;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ConversationEscalationApiTest extends TestCase
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

    public function test_student_can_escalate_accessible_conversation_to_admin_review(): void
    {
        $conversation = $this->createConversation([$this->student, $this->teacher]);
        $issueReport = IssueReport::query()->create([
            'issue_type' => IssueReport::TYPE_STUDENT_CONCERN,
            'status' => IssueReport::STATUS_OPEN,
            'priority' => IssueReport::PRIORITY_NORMAL,
            'reporter_id' => $this->student->id,
            'related_student_id' => $this->student->id,
            'related_teacher_id' => $this->teacher->id,
            'title' => 'Classroom concern',
            'description' => 'Student needs admin support for a chat concern.',
        ]);

        Sanctum::actingAs($this->student);

        $response = $this->postJson("/api/v1/conversations/{$conversation->public_id}/escalations", [
            'reason' => 'The conversation needs admin review.',
            'notes' => 'Please review before the next lesson.',
            'issue_report_id' => $issueReport->public_id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.conversation_id', $conversation->public_id)
            ->assertJsonPath('data.message_id', null)
            ->assertJsonPath('data.issue_report_id', $issueReport->public_id)
            ->assertJsonPath('data.status', ConversationEscalation::STATUS_OPEN)
            ->assertJsonPath('data.reason', 'The conversation needs admin review.')
            ->assertJsonPath('data.notes', 'Please review before the next lesson.')
            ->assertJsonPath('data.escalated_by', $this->student->public_id)
            ->assertJsonMissingPath('data.review_notes');

        $this->assertDatabaseHas('conversation_escalations', [
            'public_id' => $response->json('data.id'),
            'conversation_id' => $conversation->id,
            'conversation_message_id' => null,
            'escalated_by' => $this->student->id,
            'issue_report_id' => $issueReport->id,
            'status' => ConversationEscalation::STATUS_OPEN,
        ]);
    }

    public function test_teacher_can_escalate_specific_message_for_assigned_student_conversation(): void
    {
        $conversation = $this->createConversation([$this->student, $this->teacher]);
        $message = $this->createMessage($conversation, $this->student, 'I need help with this situation.');

        Sanctum::actingAs($this->teacher);

        $this->postJson("/api/v1/conversations/{$conversation->public_id}/messages/{$message->public_id}/escalations", [
            'reason' => 'Student message needs admin review.',
            'notes' => 'Escalating the exact message.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.conversation_id', $conversation->public_id)
            ->assertJsonPath('data.message_id', $message->public_id)
            ->assertJsonPath('data.status', ConversationEscalation::STATUS_OPEN)
            ->assertJsonPath('data.escalated_by', $this->teacher->public_id);

        $this->assertDatabaseHas('conversation_escalations', [
            'conversation_id' => $conversation->id,
            'conversation_message_id' => $message->id,
            'escalated_by' => $this->teacher->id,
        ]);
    }

    public function test_users_cannot_escalate_conversations_they_cannot_access_or_are_not_assigned_to(): void
    {
        $conversation = $this->createConversation([$this->student, $this->teacher]);
        $unassignedConversation = $this->createConversation([$this->otherStudent, $this->teacher], [
            'student_id' => $this->otherStudent->id,
            'teacher_id' => $this->teacher->id,
        ]);

        Sanctum::actingAs($this->otherStudent);

        $this->postJson("/api/v1/conversations/{$conversation->public_id}/escalations", [
            'reason' => 'I should not reach this conversation.',
        ])->assertNotFound();

        Sanctum::actingAs($this->teacher);

        $this->postJson("/api/v1/conversations/{$unassignedConversation->public_id}/escalations", [
            'reason' => 'Teacher is not the assigned teacher for this student.',
        ])->assertForbidden();

        Sanctum::actingAs($this->admin);

        $this->postJson("/api/v1/conversations/{$conversation->public_id}/escalations", [
            'reason' => 'Admins review escalations instead of creating them.',
        ])->assertForbidden();
    }

    public function test_admin_queue_exposes_and_resolves_escalations(): void
    {
        $conversation = $this->createConversation([$this->student, $this->teacher]);
        $message = $this->createMessage($conversation, $this->student, 'Please escalate this.');
        $escalation = ConversationEscalation::query()->create([
            'conversation_id' => $conversation->id,
            'conversation_message_id' => $message->id,
            'escalated_by' => $this->student->id,
            'status' => ConversationEscalation::STATUS_OPEN,
            'reason' => 'Needs admin review.',
            'notes' => 'Student provided extra context.',
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/admin/chat-escalations?status=open')
            ->assertOk()
            ->assertJsonPath('data.0.id', $escalation->public_id)
            ->assertJsonPath('data.0.conversation_id', $conversation->public_id)
            ->assertJsonPath('data.0.message_id', $message->public_id)
            ->assertJsonPath('data.0.conversation.id', $conversation->public_id)
            ->assertJsonPath('data.0.message.id', $message->public_id)
            ->assertJsonPath('data.0.escalated_by_user.id', $this->student->public_id);

        $this->patchJson("/api/v1/admin/chat-escalations/{$escalation->public_id}/status", [
            'status' => ConversationEscalation::STATUS_RESOLVED,
            'review_notes' => 'Reviewed and handled.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', ConversationEscalation::STATUS_RESOLVED)
            ->assertJsonPath('data.review_notes', 'Reviewed and handled.')
            ->assertJsonPath('data.reviewed_by', $this->admin->public_id)
            ->assertJsonPath('data.resolved_at', fn ($value) => $value !== null)
            ->assertJsonPath('data.dismissed_at', null);

        $this->assertDatabaseHas('conversation_escalations', [
            'id' => $escalation->id,
            'status' => ConversationEscalation::STATUS_RESOLVED,
            'reviewed_by' => $this->admin->id,
        ]);
    }

    public function test_staff_queue_access_depends_on_escalation_permissions(): void
    {
        $conversation = $this->createConversation([$this->student, $this->teacher]);
        $escalation = ConversationEscalation::query()->create([
            'conversation_id' => $conversation->id,
            'escalated_by' => $this->student->id,
            'status' => ConversationEscalation::STATUS_OPEN,
            'reason' => 'Needs admin review.',
        ]);
        $staff = $this->userWithRole('staff');

        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/admin/chat-escalations')->assertForbidden();

        $staff->givePermissionTo('chat_escalations.view');

        $this->getJson('/api/v1/admin/chat-escalations')
            ->assertOk()
            ->assertJsonPath('data.0.id', $escalation->public_id);

        $this->patchJson("/api/v1/admin/chat-escalations/{$escalation->public_id}/status", [
            'status' => ConversationEscalation::STATUS_IN_REVIEW,
        ])->assertForbidden();

        $staff->givePermissionTo('chat_escalations.manage');

        $this->patchJson("/api/v1/admin/chat-escalations/{$escalation->public_id}/status", [
            'status' => ConversationEscalation::STATUS_IN_REVIEW,
            'review_notes' => 'Taking a first look.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', ConversationEscalation::STATUS_IN_REVIEW)
            ->assertJsonPath('data.reviewed_by', $staff->public_id);
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

    private function createMessage(Conversation $conversation, User $sender, string $body): ConversationMessage
    {
        return ConversationMessage::query()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'body' => $body,
            'status' => ConversationMessage::STATUS_SENT,
        ]);
    }
}
