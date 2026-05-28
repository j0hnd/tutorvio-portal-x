<?php

namespace Tests\Feature;

use App\Models\IssueReport;
use App\Models\Lesson;
use App\Models\TeacherStudentAssignment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class IssueReportApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_student_can_create_issue_for_own_lesson_with_normalized_type_and_open_status(): void
    {
        [$student, $teacher] = $this->createStudentAndTeacher();
        $lesson = $this->createLesson($student, $teacher);

        Sanctum::actingAs($student);

        $this->postJson('/api/v1/issue-reports', [
            'type' => 'student absent issue form',
            'lesson_id' => $lesson->id,
            'related_teacher_id' => $teacher->id,
            'title' => 'Student missed class',
            'description' => 'The student was unable to attend the scheduled class.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', IssueReport::TYPE_STUDENT_ABSENT_ISSUE_FORM)
            ->assertJsonPath('data.status', IssueReport::STATUS_OPEN)
            ->assertJsonPath('data.reporter_id', $student->id)
            ->assertJsonPath('data.related_student_id', $student->id)
            ->assertJsonPath('data.related_teacher_id', $teacher->id)
            ->assertJsonPath('data.lesson_id', $lesson->id);

        $this->assertDatabaseHas('issue_reports', [
            'issue_type' => IssueReport::TYPE_STUDENT_ABSENT_ISSUE_FORM,
            'status' => IssueReport::STATUS_OPEN,
            'reporter_id' => $student->id,
            'related_student_id' => $student->id,
            'lesson_id' => $lesson->id,
        ]);
    }

    public function test_issue_type_requirements_are_validated(): void
    {
        [$student] = $this->createStudentAndTeacher();

        Sanctum::actingAs($student);

        $this->postJson('/api/v1/issue-reports', [
            'issue_type' => IssueReport::TYPE_CLASS_INCIDENT,
            'title' => 'Class incident',
            'description' => 'Something happened in class.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lesson_id');
    }

    public function test_student_cannot_create_issue_for_another_students_lesson(): void
    {
        [$student, $teacher] = $this->createStudentAndTeacher();
        $otherStudent = $this->createRoleUser('student');
        $lesson = $this->createLesson($otherStudent, $teacher);

        Sanctum::actingAs($student);

        $this->postJson('/api/v1/issue-reports', [
            'issue_type' => IssueReport::TYPE_CLASS_INCIDENT,
            'lesson_id' => $lesson->id,
            'title' => 'Class incident',
            'description' => 'This is not my lesson.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lesson_id');
    }

    public function test_teacher_can_create_issue_for_assigned_student(): void
    {
        [$student, $teacher] = $this->createStudentAndTeacher();
        $this->assignStudentToTeacher($student, $teacher);

        Sanctum::actingAs($teacher);

        $this->postJson('/api/v1/issue-reports', [
            'issue_type' => IssueReport::TYPE_STUDENT_CONCERN,
            'related_student_id' => $student->id,
            'title' => 'Student concern',
            'description' => 'Needs operational follow-up.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', IssueReport::STATUS_OPEN)
            ->assertJsonPath('data.reporter_id', $teacher->id)
            ->assertJsonPath('data.related_student_id', $student->id)
            ->assertJsonPath('data.related_teacher_id', $teacher->id);
    }

    public function test_teacher_cannot_create_issue_for_unassigned_student(): void
    {
        [$student, $teacher] = $this->createStudentAndTeacher();

        Sanctum::actingAs($teacher);

        $this->postJson('/api/v1/issue-reports', [
            'issue_type' => IssueReport::TYPE_STUDENT_CONCERN,
            'related_student_id' => $student->id,
            'title' => 'Student concern',
            'description' => 'This student is not assigned to me.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('related_student_id');
    }

    public function test_admin_and_permitted_staff_can_create_issue_on_behalf_of_user(): void
    {
        [$student, $teacher] = $this->createStudentAndTeacher();
        $admin = $this->createRoleUser('admin');

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/issue-reports', [
            'issue_type' => IssueReport::TYPE_TEACHER_CONCERN,
            'target_user_id' => $student->id,
            'related_student_id' => $student->id,
            'related_teacher_id' => $teacher->id,
            'title' => 'Parent concern',
            'description' => 'Submitted by staff on behalf of the student.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.reporter_id', $admin->id)
            ->assertJsonPath('data.target_user_id', $student->id);

        $staff = $this->createRoleUser('staff');
        Sanctum::actingAs($staff);

        $this->postJson('/api/v1/issue-reports', [
            'issue_type' => IssueReport::TYPE_TECHNICAL_ISSUE,
            'target_user_id' => $student->id,
            'title' => 'Login trouble',
            'description' => 'The student cannot sign in.',
        ])->assertForbidden();

        $staff->givePermissionTo('issue_reports.create');

        $this->postJson('/api/v1/issue-reports', [
            'issue_type' => IssueReport::TYPE_TECHNICAL_ISSUE,
            'target_user_id' => $student->id,
            'title' => 'Login trouble',
            'description' => 'The student cannot sign in.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.reporter_id', $staff->id)
            ->assertJsonPath('data.target_user_id', $student->id);
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function createStudentAndTeacher(): array
    {
        return [
            $this->createRoleUser('student'),
            $this->createRoleUser('teacher'),
        ];
    }

    private function createRoleUser(string $role): User
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $user->assignRole($role);

        return $user;
    }

    private function createLesson(User $student, User $teacher): Lesson
    {
        return Lesson::create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'start_time' => '2026-06-01 09:00:00',
            'end_time' => '2026-06-01 10:00:00',
            'status' => Lesson::STATUS_SCHEDULED,
        ]);
    }

    private function assignStudentToTeacher(User $student, User $teacher): void
    {
        TeacherStudentAssignment::create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'assigned_at' => now(),
            'status' => TeacherStudentAssignment::STATUS_ACTIVE,
            'active_student_id' => $student->id,
        ]);
    }
}
