<?php

namespace Tests\Feature;

use App\Models\Homework;
use App\Models\Invoice;
use App\Models\MessageThread;
use App\Models\StudentProgressRecord;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PublicIdAuthorizationRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['billing.invoice.student_visibility_enabled' => true]);
    }

    public function test_student_cannot_access_another_students_invoice_using_valid_public_id(): void
    {
        $student = $this->userWithRole('student');
        $otherStudent = $this->userWithRole('student');
        $invoice = Invoice::factory()->create([
            'public_id' => (string) Str::ulid(),
            'student_id' => $otherStudent->id,
            'payment_reference' => 'must-not-leak',
        ]);

        Sanctum::actingAs($student);

        $this->getJson("/api/v1/invoices/{$invoice->public_id}")
            ->assertForbidden()
            ->assertJsonMissing(['payment_reference' => 'must-not-leak']);
    }

    public function test_student_cannot_access_another_students_homework_using_valid_public_id(): void
    {
        $teacher = $this->userWithRole('teacher');
        $otherTeacher = $this->userWithRole('teacher');
        $student = $this->userWithRole('student', assignedTeacher: $teacher);
        $otherStudent = $this->userWithRole('student', assignedTeacher: $otherTeacher);
        $homework = Homework::factory()->create([
            'public_id' => (string) Str::ulid(),
            'student_id' => $otherStudent->id,
            'teacher_id' => $otherTeacher->id,
            'title' => 'Other student homework',
        ]);

        Sanctum::actingAs($student);

        $this->getJson("/api/v1/homeworks/{$homework->public_id}")
            ->assertForbidden()
            ->assertJsonMissing(['title' => 'Other student homework']);
    }

    public function test_student_cannot_access_another_users_profile_using_valid_public_id(): void
    {
        $student = $this->userWithRole('student');
        $otherStudent = $this->userWithRole('student');

        Sanctum::actingAs($student);

        $this->getJson("/api/v1/users/{$otherStudent->public_id}/profile")
            ->assertForbidden()
            ->assertJsonMissing(['id' => $otherStudent->public_id]);
    }

    public function test_teacher_cannot_access_unrelated_student_records_using_valid_public_id(): void
    {
        $teacher = $this->userWithRole('teacher');
        $otherTeacher = $this->userWithRole('teacher');
        $assignedStudent = $this->userWithRole('student', assignedTeacher: $teacher);
        $unrelatedStudent = $this->userWithRole('student', assignedTeacher: $otherTeacher);
        $assignedRecord = StudentProgressRecord::factory()->create([
            'public_id' => (string) Str::ulid(),
            'student_id' => $assignedStudent->id,
            'teacher_id' => $teacher->id,
        ]);
        $unrelatedRecord = StudentProgressRecord::factory()->create([
            'public_id' => (string) Str::ulid(),
            'student_id' => $unrelatedStudent->id,
            'teacher_id' => $otherTeacher->id,
            'teacher_comments' => 'Unrelated private progress note',
        ]);

        Sanctum::actingAs($teacher);

        $this->getJson("/api/v1/student-progress-records/{$assignedRecord->public_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $assignedRecord->public_id);

        $this->getJson("/api/v1/student-progress-records/{$unrelatedRecord->public_id}")
            ->assertForbidden()
            ->assertJsonMissing(['teacher_comments' => 'Unrelated private progress note']);
    }

    public function test_user_cannot_access_message_thread_they_are_not_part_of_using_valid_public_id(): void
    {
        $teacher = $this->userWithRole('teacher');
        $student = $this->userWithRole('student', assignedTeacher: $teacher);
        $outsider = $this->userWithRole('student');
        $thread = MessageThread::factory()->create([
            'public_id' => (string) Str::ulid(),
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'created_by' => $student->id,
        ]);
        $thread->participants()->create([
            'user_id' => $student->id,
            'participant_role' => 'student',
            'last_read_at' => now(),
        ]);
        $thread->participants()->create([
            'user_id' => $teacher->id,
            'participant_role' => 'teacher',
        ]);

        Sanctum::actingAs($outsider);

        $this->getJson("/api/v1/message-threads/{$thread->public_id}/messages")
            ->assertNotFound();
    }

    public function test_invalid_public_id_returns_not_found(): void
    {
        $student = $this->userWithRole('student');
        $missingPublicId = (string) Str::ulid();

        Sanctum::actingAs($student);

        $this->getJson("/api/v1/invoices/{$missingPublicId}")
            ->assertNotFound();
    }

    public function test_valid_public_id_with_unauthorized_user_returns_safe_unauthorized_response(): void
    {
        $teacher = $this->userWithRole('teacher');
        $otherTeacher = $this->userWithRole('teacher');
        $unrelatedStudent = $this->userWithRole('student', assignedTeacher: $otherTeacher);

        Sanctum::actingAs($teacher);

        $this->getJson("/api/v1/users/{$unrelatedStudent->public_id}/profile")
            ->assertNotFound()
            ->assertJsonMissing(['id' => $unrelatedStudent->public_id]);
    }

    public function test_admin_and_staff_access_still_works_when_permission_allows(): void
    {
        $admin = $this->userWithRole('admin');
        $staff = $this->userWithRole('staff');
        $staff->givePermissionTo([
            'homeworks.view',
            'invoices.view',
            'messages.view',
            'student_progress_records.view',
            'users.view',
        ]);

        $teacher = $this->userWithRole('teacher');
        $student = $this->userWithRole('student', assignedTeacher: $teacher);
        $invoice = Invoice::factory()->create([
            'public_id' => (string) Str::ulid(),
            'student_id' => $student->id,
        ]);
        $homework = Homework::factory()->create([
            'public_id' => (string) Str::ulid(),
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
        ]);
        $record = StudentProgressRecord::factory()->create([
            'public_id' => (string) Str::ulid(),
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
        ]);
        $thread = MessageThread::factory()->create([
            'public_id' => (string) Str::ulid(),
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'created_by' => $student->id,
        ]);
        $thread->participants()->create([
            'user_id' => $student->id,
            'participant_role' => 'student',
        ]);
        $thread->participants()->create([
            'user_id' => $teacher->id,
            'participant_role' => 'teacher',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson("/api/v1/homeworks/{$homework->public_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $homework->public_id);

        Sanctum::actingAs($staff);

        $this->getJson("/api/v1/invoices/{$invoice->public_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $invoice->public_id);

        $this->getJson("/api/v1/users/{$student->public_id}/profile")
            ->assertOk()
            ->assertJsonPath('data.id', $student->public_id);

        $this->getJson("/api/v1/student-progress-records/{$record->public_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $record->public_id);

        $this->getJson("/api/v1/message-threads/{$thread->public_id}/messages")
            ->assertOk();
    }

    private function userWithRole(string $role, array $attributes = [], ?User $assignedTeacher = null): User
    {
        $user = User::factory()->create([
            'public_id' => (string) Str::ulid(),
            'status' => User::STATUS_ACTIVE,
            ...$attributes,
        ]);
        $user->assignRole($role);

        if ($role === 'student') {
            $user->studentProfile()->create([
                'assigned_teacher_id' => $assignedTeacher?->id,
            ]);
        }

        return $user;
    }
}
