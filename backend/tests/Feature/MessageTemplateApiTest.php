<?php

namespace Tests\Feature;

use App\Models\MessageTemplate;
use App\Models\User;
use Database\Seeders\MessageTemplateSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MessageTemplateApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_global_message_templates(): void
    {
        $admin = $this->createRoleUser('admin');
        Sanctum::actingAs($admin);

        $createResponse = $this->postJson('/api/v1/admin/message-templates', [
            'title' => 'Lesson reminder',
            'body' => 'Your lesson starts soon.',
            'category' => 'lesson reminder',
            'role_visibility' => ['teacher', 'admin'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Lesson reminder')
            ->assertJsonPath('data.category', MessageTemplate::CATEGORY_LESSON_REMINDER)
            ->assertJsonPath('data.status', MessageTemplate::STATUS_ACTIVE)
            ->assertJsonPath('data.created_by', $admin->public_id);

        $templateId = $createResponse->json('data.id');

        $this->patchJson("/api/v1/admin/message-templates/{$templateId}", [
            'title' => 'Updated lesson reminder',
            'status' => MessageTemplate::STATUS_INACTIVE,
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated lesson reminder')
            ->assertJsonPath('data.status', MessageTemplate::STATUS_INACTIVE)
            ->assertJsonPath('data.updated_by', $admin->public_id);

        $this->getJson('/api/v1/admin/message-templates?status=inactive')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $templateId);

        $this->assertDatabaseHas('message_templates', [
            'public_id' => $templateId,
            'title' => 'Updated lesson reminder',
            'category' => MessageTemplate::CATEGORY_LESSON_REMINDER,
            'status' => MessageTemplate::STATUS_INACTIVE,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
    }

    public function test_staff_message_template_management_depends_on_permissions(): void
    {
        $staff = $this->createRoleUser('staff');
        $template = MessageTemplate::factory()->create([
            'title' => 'Attendance follow-up',
            'category' => MessageTemplate::CATEGORY_ATTENDANCE_FOLLOW_UP,
            'role_visibility' => [MessageTemplate::ROLE_TEACHER],
        ]);
        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/admin/message-templates')->assertForbidden();
        $this->postJson('/api/v1/admin/message-templates', [
            'title' => 'Homework reminder',
            'body' => 'Please complete your homework.',
            'category' => MessageTemplate::CATEGORY_HOMEWORK_REMINDER,
            'role_visibility' => ['teacher'],
        ])->assertForbidden();

        $staff->givePermissionTo('message_templates.view');

        $this->getJson('/api/v1/admin/message-templates')
            ->assertOk()
            ->assertJsonFragment(['id' => $template->public_id]);

        $this->patchJson("/api/v1/admin/message-templates/{$template->public_id}", [
            'title' => 'Updated attendance follow-up',
        ])->assertForbidden();

        $staff->givePermissionTo('message_templates.manage');

        $this->patchJson("/api/v1/admin/message-templates/{$template->public_id}", [
            'title' => 'Updated attendance follow-up',
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated attendance follow-up')
            ->assertJsonPath('data.updated_by', $staff->public_id);
    }

    public function test_teacher_sees_global_and_own_teacher_specific_active_templates_only(): void
    {
        $teacher = $this->createRoleUser('teacher');
        $otherTeacher = $this->createRoleUser('teacher');
        $globalTemplate = MessageTemplate::factory()->create([
            'title' => 'Progress check-in',
            'category' => MessageTemplate::CATEGORY_PROGRESS_CHECK_IN,
            'role_visibility' => [MessageTemplate::ROLE_TEACHER],
            'status' => MessageTemplate::STATUS_ACTIVE,
            'teacher_id' => null,
        ]);
        $ownTemplate = MessageTemplate::factory()->create([
            'title' => 'My reschedule notice',
            'category' => MessageTemplate::CATEGORY_RESCHEDULE_NOTICE,
            'role_visibility' => [MessageTemplate::ROLE_TEACHER],
            'status' => MessageTemplate::STATUS_ACTIVE,
            'teacher_id' => $teacher->id,
        ]);
        $otherTemplate = MessageTemplate::factory()->create([
            'title' => 'Other teacher notice',
            'role_visibility' => [MessageTemplate::ROLE_TEACHER],
            'status' => MessageTemplate::STATUS_ACTIVE,
            'teacher_id' => $otherTeacher->id,
        ]);
        MessageTemplate::factory()->create([
            'title' => 'Inactive homework reminder',
            'role_visibility' => [MessageTemplate::ROLE_TEACHER],
            'status' => MessageTemplate::STATUS_INACTIVE,
            'teacher_id' => null,
        ]);
        MessageTemplate::factory()->create([
            'title' => 'Admin payment reminder',
            'category' => MessageTemplate::CATEGORY_PAYMENT_REMINDER,
            'role_visibility' => [MessageTemplate::ROLE_ADMIN, MessageTemplate::ROLE_STAFF],
            'status' => MessageTemplate::STATUS_ACTIVE,
            'teacher_id' => null,
        ]);

        Sanctum::actingAs($teacher);

        $this->getJson('/api/v1/message-templates')
            ->assertOk()
            ->assertJsonFragment(['id' => $globalTemplate->public_id])
            ->assertJsonFragment(['id' => $ownTemplate->public_id])
            ->assertJsonMissing(['id' => $otherTemplate->public_id])
            ->assertJsonMissing(['title' => 'Inactive homework reminder'])
            ->assertJsonMissing(['title' => 'Admin payment reminder']);

        $this->getJson("/api/v1/message-templates/{$ownTemplate->public_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $ownTemplate->public_id)
            ->assertJsonPath('data.teacher_id', $teacher->public_id);

        $this->getJson("/api/v1/message-templates/{$otherTemplate->public_id}")
            ->assertNotFound();
    }

    public function test_student_cannot_manage_templates_and_only_sees_student_visible_templates(): void
    {
        $student = $this->createRoleUser('student');
        $studentTemplate = MessageTemplate::factory()->create([
            'title' => 'Student reminder',
            'role_visibility' => [MessageTemplate::ROLE_STUDENT],
            'status' => MessageTemplate::STATUS_ACTIVE,
        ]);
        $teacherTemplate = MessageTemplate::factory()->create([
            'title' => 'Teacher homework reminder',
            'role_visibility' => [MessageTemplate::ROLE_TEACHER],
            'status' => MessageTemplate::STATUS_ACTIVE,
        ]);

        Sanctum::actingAs($student);

        $this->postJson('/api/v1/admin/message-templates', [
            'title' => 'Payment reminder',
            'body' => 'Please check your billing details.',
            'category' => MessageTemplate::CATEGORY_PAYMENT_REMINDER,
            'role_visibility' => ['student'],
        ])->assertForbidden();

        $this->getJson('/api/v1/message-templates')
            ->assertOk()
            ->assertJsonFragment(['id' => $studentTemplate->public_id])
            ->assertJsonMissing(['id' => $teacherTemplate->public_id]);
    }

    public function test_teacher_specific_templates_require_teacher_public_id(): void
    {
        $admin = $this->createRoleUser('admin');
        $student = $this->createRoleUser('student');
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/message-templates', [
            'title' => 'Teacher-specific notice',
            'body' => 'Please send this to your student.',
            'category' => MessageTemplate::CATEGORY_RESCHEDULE_NOTICE,
            'role_visibility' => ['teacher'],
            'teacher_id' => $student->public_id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('teacher_id');
    }

    public function test_default_templates_are_seeded_for_expected_roles(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'status' => User::STATUS_ACTIVE,
        ]);
        $admin->assignRole('admin');

        $this->seed(MessageTemplateSeeder::class);

        $this->assertDatabaseHas('message_templates', [
            'title' => 'Lesson reminder',
            'category' => MessageTemplate::CATEGORY_LESSON_REMINDER,
            'status' => MessageTemplate::STATUS_ACTIVE,
            'created_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('message_templates', [
            'title' => 'Payment reminder',
            'category' => MessageTemplate::CATEGORY_PAYMENT_REMINDER,
            'status' => MessageTemplate::STATUS_ACTIVE,
        ]);

        Sanctum::actingAs($this->createRoleUser('teacher'));

        $this->getJson('/api/v1/message-templates')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Lesson reminder'])
            ->assertJsonMissing(['title' => 'Payment reminder']);
    }

    private function createRoleUser(string $role): User
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $user->assignRole($role);

        return $user;
    }
}
