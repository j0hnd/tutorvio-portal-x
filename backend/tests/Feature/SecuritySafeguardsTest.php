<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\LessonNote;
use App\Models\StudentProgressRecord;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SecuritySafeguardsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_student_cannot_access_another_students_progress_records(): void
    {
        $teacher = $this->userWithRole('teacher');
        $student = $this->userWithRole('student');
        $otherStudent = $this->userWithRole('student');

        $student->studentProfile()->create(['assigned_teacher_id' => $teacher->id]);
        $otherStudent->studentProfile()->create(['assigned_teacher_id' => $teacher->id]);

        $ownRecord = StudentProgressRecord::factory()->create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
        ]);
        $otherRecord = StudentProgressRecord::factory()->create([
            'student_id' => $otherStudent->id,
            'teacher_id' => $teacher->id,
        ]);

        Sanctum::actingAs($student);

        $this->getJson("/api/v1/student-progress-records/{$ownRecord->public_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $ownRecord->public_id);

        $this->getJson("/api/v1/student-progress-records/{$otherRecord->public_id}")
            ->assertForbidden()
            ->assertJsonMissing(['id' => $otherRecord->public_id]);
    }

    public function test_teacher_cannot_access_unassigned_student_progress_records(): void
    {
        $teacher = $this->userWithRole('teacher');
        $otherTeacher = $this->userWithRole('teacher');
        $assignedStudent = $this->userWithRole('student');
        $unassignedStudent = $this->userWithRole('student');

        $assignedStudent->studentProfile()->create(['assigned_teacher_id' => $teacher->id]);
        $unassignedStudent->studentProfile()->create(['assigned_teacher_id' => $otherTeacher->id]);

        $assignedRecord = StudentProgressRecord::factory()->create([
            'student_id' => $assignedStudent->id,
            'teacher_id' => $teacher->id,
        ]);
        $unassignedRecord = StudentProgressRecord::factory()->create([
            'student_id' => $unassignedStudent->id,
            'teacher_id' => $otherTeacher->id,
        ]);

        Sanctum::actingAs($teacher);

        $this->getJson("/api/v1/student-progress-records/{$assignedRecord->public_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $assignedRecord->public_id);

        $this->getJson("/api/v1/student-progress-records/{$unassignedRecord->public_id}")
            ->assertForbidden()
            ->assertJsonMissing(['id' => $unassignedRecord->public_id]);
    }

    public function test_student_cannot_see_lesson_note_internal_remarks_or_review_fields(): void
    {
        $teacher = $this->userWithRole('teacher');
        $student = $this->userWithRole('student');
        $student->studentProfile()->create(['assigned_teacher_id' => $teacher->id]);

        $lesson = Lesson::create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'start_time' => '2026-06-01 09:00:00',
            'end_time' => '2026-06-01 10:00:00',
            'status' => Lesson::STATUS_COMPLETED,
        ]);

        $lessonNote = LessonNote::create([
            'lesson_id' => $lesson->id,
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'author_id' => $teacher->id,
            'lesson_objective' => 'Practice introductions.',
            'internal_note' => 'Teacher-only coaching remark.',
            'review_status' => LessonNote::REVIEW_STATUS_FLAGGED,
            'review_note' => 'Internal reviewer note.',
            'reviewed_by' => $teacher->id,
            'reviewed_at' => now(),
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($student);

        $this->getJson("/api/v1/lesson-notes/{$lessonNote->public_id}")
            ->assertOk()
            ->assertJsonPath('data.lesson_objective', 'Practice introductions.')
            ->assertJsonMissingPath('data.internal_note')
            ->assertJsonMissingPath('data.review_status')
            ->assertJsonMissingPath('data.review_note')
            ->assertJsonMissing(['Teacher-only coaching remark.'])
            ->assertJsonMissing(['Internal reviewer note.']);
    }

    public function test_student_cannot_see_admin_or_internal_profile_fields(): void
    {
        $teacher = $this->userWithRole('teacher');
        $student = $this->userWithRole('student', [
            'signed_document_path' => 'contracts/private/student-agreement.pdf',
            'profile_photo_path' => 'profiles/private/student.jpg',
        ]);
        $student->studentProfile()->create([
            'assigned_teacher_id' => $teacher->id,
            'notes' => 'Administrative note.',
            'teacher_notes' => 'Teacher-only note.',
            'internal_notes' => 'Internal admin note.',
        ]);

        Sanctum::actingAs($student);

        $this->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonMissingPath('data.roles')
            ->assertJsonMissingPath('data.status')
            ->assertJsonMissingPath('data.signed_document_path')
            ->assertJsonMissingPath('data.profile_photo_path')
            ->assertJsonMissingPath('data.student_profile.assigned_teacher_id')
            ->assertJsonMissingPath('data.student_profile.notes')
            ->assertJsonMissingPath('data.student_profile.teacher_notes')
            ->assertJsonMissingPath('data.student_profile.internal_notes')
            ->assertJsonMissing(['contracts/private/student-agreement.pdf'])
            ->assertJsonMissing(['Internal admin note.']);
    }

    private function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            ...$attributes,
        ]);
        $user->assignRole($role);

        return $user;
    }
}
