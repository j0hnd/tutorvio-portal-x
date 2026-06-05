<?php

namespace Tests\Feature;

use App\Models\Conversation;
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
            ->assertJsonPath('data.participants.0.user_id', $this->student->public_id)
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
}
