<?php

namespace Tests\Feature;

use App\Models\IssueReport;
use App\Models\Lesson;
use App\Models\TeacherStudentAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;
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
            ->assertJsonPath('data.reporter_id', $student->public_id)
            ->assertJsonPath('data.related_student_id', $student->public_id)
            ->assertJsonPath('data.related_teacher_id', $teacher->public_id)
            ->assertJsonPath('data.lesson_id', $lesson->public_id);

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
            'issue_type' => 'parent_billing_complaint',
            'title' => 'Invalid issue type',
            'description' => 'This issue type is not supported.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('issue_type');

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
            ->assertJsonPath('data.reporter_id', $teacher->public_id)
            ->assertJsonPath('data.related_student_id', $student->public_id)
            ->assertJsonPath('data.related_teacher_id', $teacher->public_id);
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
            ->assertJsonPath('data.reporter_id', $admin->public_id)
            ->assertJsonPath('data.target_user_id', $student->public_id);

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
            ->assertJsonPath('data.reporter_id', $staff->public_id)
            ->assertJsonPath('data.target_user_id', $student->public_id);
    }

    public function test_admin_can_list_and_filter_issue_reports(): void
    {
        [$student, $teacher] = $this->createStudentAndTeacher();
        $admin = $this->createRoleUser('admin');
        $staff = $this->createRoleUser('staff');
        $lesson = $this->createLesson($student, $teacher);
        $matchingIssue = $this->createIssueReport($student, [
            'issue_type' => IssueReport::TYPE_CLASS_INCIDENT,
            'status' => IssueReport::STATUS_IN_PROGRESS,
            'priority' => IssueReport::PRIORITY_HIGH,
            'assigned_to_id' => $staff->id,
            'related_student_id' => $student->id,
            'related_teacher_id' => $teacher->id,
            'lesson_id' => $lesson->id,
            'created_at' => CarbonImmutable::parse('2026-06-10 10:00:00'),
        ]);
        $this->createIssueReport($teacher, [
            'issue_type' => IssueReport::TYPE_TECHNICAL_ISSUE,
            'status' => IssueReport::STATUS_OPEN,
            'priority' => IssueReport::PRIORITY_NORMAL,
            'created_at' => CarbonImmutable::parse('2026-05-01 10:00:00'),
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/issue-reports?status=in_progress&issue_type=class_incident&priority=high&reporter_id='.$student->id.'&assigned_to_id='.$staff->id.'&related_student_id='.$student->id.'&related_teacher_id='.$teacher->id.'&lesson_id='.$lesson->id.'&date_from=2026-06-01&date_to=2026-06-30')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matchingIssue->public_id)
            ->assertJsonPath('data.0.description', 'Issue details.');
    }

    public function test_admin_can_assign_resolve_close_and_audit_issue_report(): void
    {
        [$student] = $this->createStudentAndTeacher();
        $admin = $this->createRoleUser('admin');
        $staff = $this->createRoleUser('staff');
        $issue = $this->createIssueReport($student);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/admin/issue-reports/{$issue->public_id}/assignment", [
            'assigned_to_id' => $staff->id,
            'note' => 'Assigned to operations.',
        ])
            ->assertOk()
            ->assertJsonPath('data.assigned_to_id', $staff->public_id)
            ->assertJsonPath('data.status', IssueReport::STATUS_IN_PROGRESS);

        $this->patchJson("/api/v1/admin/issue-reports/{$issue->public_id}/status", [
            'status' => IssueReport::STATUS_RESOLVED,
            'note' => 'Resolved after follow-up.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', IssueReport::STATUS_RESOLVED)
            ->assertJsonPath('data.resolved_by', $admin->public_id);

        $this->postJson("/api/v1/admin/issue-reports/{$issue->public_id}/resolution-notes", [
            'resolution_notes' => 'Parent and teacher were notified.',
        ])
            ->assertOk()
            ->assertJsonPath('data.resolution_notes', 'Parent and teacher were notified.');

        $this->postJson("/api/v1/admin/issue-reports/{$issue->public_id}/close", [
            'note' => 'Closed by admin.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', IssueReport::STATUS_CLOSED);

        $this->assertDatabaseHas('issue_reports', [
            'id' => $issue->id,
            'assigned_to_id' => $staff->id,
            'status' => IssueReport::STATUS_CLOSED,
            'resolution_notes' => 'Parent and teacher were notified.',
            'resolved_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('issue_comments', [
            'issue_report_id' => $issue->id,
            'body' => 'Assigned to operations.',
            'comment_type' => 'status_change',
        ]);
        $this->assertDatabaseHas('issue_comments', [
            'issue_report_id' => $issue->id,
            'body' => 'Parent and teacher were notified.',
            'comment_type' => 'resolution_note',
            'is_internal' => true,
        ]);
    }

    public function test_admin_can_cancel_issue_report_with_resolution_notes(): void
    {
        [$student] = $this->createStudentAndTeacher();
        $admin = $this->createRoleUser('admin');
        $issue = $this->createIssueReport($student);

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/issue-reports/{$issue->public_id}/cancel", [
            'note' => 'Duplicate report.',
            'resolution_notes' => 'Cancelled after confirming it duplicates another ticket.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', IssueReport::STATUS_CANCELLED)
            ->assertJsonPath('data.resolved_by', $admin->public_id)
            ->assertJsonPath('data.resolution_notes', 'Cancelled after confirming it duplicates another ticket.');

        $this->assertDatabaseHas('issue_reports', [
            'id' => $issue->id,
            'status' => IssueReport::STATUS_CANCELLED,
            'resolved_by' => $admin->id,
            'resolution_notes' => 'Cancelled after confirming it duplicates another ticket.',
        ]);
        $this->assertDatabaseHas('issue_comments', [
            'issue_report_id' => $issue->id,
            'body' => 'Duplicate report.',
            'comment_type' => 'status_change',
        ]);
        $this->assertDatabaseHas('issue_comments', [
            'issue_report_id' => $issue->id,
            'body' => 'Cancelled after confirming it duplicates another ticket.',
            'comment_type' => 'resolution_note',
            'is_internal' => true,
        ]);
    }

    public function test_staff_permissions_and_reporter_limited_status_access_are_enforced(): void
    {
        [$student] = $this->createStudentAndTeacher();
        $otherStudent = $this->createRoleUser('student');
        $staff = $this->createRoleUser('staff');
        $issue = $this->createIssueReport($student, [
            'issue_type' => IssueReport::TYPE_STUDENT_CONCERN,
            'description' => 'Sensitive concern details.',
        ]);

        Sanctum::actingAs($staff);
        $this->getJson("/api/v1/admin/issue-reports/{$issue->public_id}")->assertForbidden();

        $staff->givePermissionTo('issue_reports.view');
        $this->getJson("/api/v1/admin/issue-reports/{$issue->public_id}")
            ->assertOk()
            ->assertJsonPath('data.description', 'Sensitive concern details.');

        Sanctum::actingAs($otherStudent);
        $this->getJson("/api/v1/issue-reports/{$issue->public_id}")->assertNotFound();

        Sanctum::actingAs($student);
        $this->getJson("/api/v1/issue-reports/{$issue->public_id}")
            ->assertOk()
            ->assertJsonPath('data.status', IssueReport::STATUS_OPEN)
            ->assertJsonMissing(['description' => 'Sensitive concern details.']);
    }

    public function test_related_users_cannot_view_unrelated_complaint_details_unless_they_reported_it(): void
    {
        [$student, $teacher] = $this->createStudentAndTeacher();
        $admin = $this->createRoleUser('admin');
        $issue = $this->createIssueReport($admin, [
            'issue_type' => IssueReport::TYPE_TEACHER_CONCERN,
            'target_user_id' => $student->id,
            'related_student_id' => $student->id,
            'related_teacher_id' => $teacher->id,
            'description' => 'Parent complaint with sensitive operational context.',
        ]);

        Sanctum::actingAs($student);
        $this->getJson("/api/v1/issue-reports/{$issue->public_id}")
            ->assertNotFound();

        Sanctum::actingAs($teacher);
        $this->getJson("/api/v1/issue-reports/{$issue->public_id}")
            ->assertNotFound();
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

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createIssueReport(User $reporter, array $attributes = []): IssueReport
    {
        $timestamps = array_intersect_key($attributes, array_flip(['created_at', 'updated_at']));
        $attributes = array_diff_key($attributes, $timestamps);

        $issueReport = IssueReport::create([
            'issue_type' => IssueReport::TYPE_TECHNICAL_ISSUE,
            'status' => IssueReport::STATUS_OPEN,
            'priority' => IssueReport::PRIORITY_NORMAL,
            'reporter_id' => $reporter->id,
            'title' => 'Issue title',
            'description' => 'Issue details.',
            ...$attributes,
        ]);

        if ($timestamps !== []) {
            $issueReport->forceFill($timestamps)->save();
        }

        return $issueReport;
    }
}
