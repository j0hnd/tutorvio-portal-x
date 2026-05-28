<?php

namespace Tests\Feature;

use App\Models\AcademicRecord;
use App\Models\CourseProgram;
use App\Models\PortalSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AcademicRecordApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    private User $teacher;

    private User $otherTeacher;

    private User $student;

    private User $otherStudent;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create(['status' => User::STATUS_ACTIVE, 'timezone' => 'Asia/Manila']);
        $this->admin->assignRole('admin');

        $this->staff = User::factory()->create(['status' => User::STATUS_ACTIVE, 'timezone' => 'Asia/Manila']);
        $this->staff->assignRole('staff');

        $this->teacher = User::factory()->create(['status' => User::STATUS_ACTIVE, 'timezone' => 'Asia/Manila']);
        $this->teacher->assignRole('teacher');

        $this->otherTeacher = User::factory()->create(['status' => User::STATUS_ACTIVE, 'timezone' => 'Asia/Manila']);
        $this->otherTeacher->assignRole('teacher');

        $this->student = User::factory()->create(['status' => User::STATUS_ACTIVE, 'timezone' => 'Asia/Manila']);
        $this->student->assignRole('student');
        $this->student->studentProfile()->create([
            'assigned_teacher_id' => $this->teacher->id,
        ]);

        $this->otherStudent = User::factory()->create(['status' => User::STATUS_ACTIVE, 'timezone' => 'Asia/Manila']);
        $this->otherStudent->assignRole('student');
        $this->otherStudent->studentProfile()->create([
            'assigned_teacher_id' => $this->otherTeacher->id,
        ]);
    }

    public function test_admin_can_create_update_view_and_archive_academic_record(): void
    {
        Sanctum::actingAs($this->admin);
        $courseProgram = CourseProgram::factory()->create();

        $response = $this->postJson('/api/v1/academic-records', [
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'course_program_id' => $courseProgram->id,
            'record_type' => AcademicRecord::TYPE_PLACEMENT,
            'title' => 'Initial placement and academic summary',
            'description' => 'Baseline academic record.',
            'recorded_on' => '2026-06-15',
            'student_level' => 'B1',
            'placement_result' => 'Placed into intermediate conversation program.',
            'course_program_history' => [
                ['program' => 'General English', 'status' => 'completed'],
            ],
            'attendance_summary' => ['completed' => 12, 'missed' => 1],
            'progress_summary' => 'Strong speaking progress.',
            'teacher_remarks' => 'Ready for guided debates.',
            'certificates' => [['name' => 'B1 Completion', 'issued_on' => '2026-06-15']],
            'completion_notes' => 'Completed first course cycle.',
            'internal_notes' => 'Discuss scholarship eligibility internally.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.student_id', $this->student->id)
            ->assertJsonPath('data.teacher_id', $this->teacher->id)
            ->assertJsonPath('data.course_program_id', $courseProgram->id)
            ->assertJsonPath('data.record_type', AcademicRecord::TYPE_PLACEMENT)
            ->assertJsonPath('data.student_level', 'B1')
            ->assertJsonPath('data.internal_notes', 'Discuss scholarship eligibility internally.')
            ->assertJsonPath('data.recorded_by', $this->admin->id);

        $recordId = $response->json('data.id');

        $this->patchJson("/api/v1/academic-records/{$recordId}", [
            'title' => 'Updated academic summary',
            'progress_summary' => 'Confident in longer speaking tasks.',
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated academic summary')
            ->assertJsonPath('data.progress_summary', 'Confident in longer speaking tasks.');

        $this->getJson("/api/v1/academic-records/{$recordId}")
            ->assertOk()
            ->assertJsonPath('data.id', $recordId)
            ->assertJsonPath('data.student.id', $this->student->id)
            ->assertJsonPath('data.course_program.id', $courseProgram->id)
            ->assertJsonPath('data.internal_notes', 'Discuss scholarship eligibility internally.');

        $this->postJson("/api/v1/academic-records/{$recordId}/archive")
            ->assertOk()
            ->assertJsonPath('data.status', AcademicRecord::STATUS_ARCHIVED)
            ->assertJsonPath('data.archived_by', $this->admin->id);

        $this->assertDatabaseHas('academic_records', [
            'id' => $recordId,
            'status' => AcademicRecord::STATUS_ARCHIVED,
            'archived_by' => $this->admin->id,
        ]);
    }

    public function test_teacher_can_view_assigned_student_records_without_internal_notes_only_when_allowed(): void
    {
        $assignedRecord = $this->createAcademicRecord([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
        ]);
        $otherRecord = $this->createAcademicRecord([
            'student_id' => $this->otherStudent->id,
            'teacher_id' => $this->otherTeacher->id,
        ]);

        Sanctum::actingAs($this->teacher);

        $this->getJson('/api/v1/academic-records?per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $assignedRecord->id)
            ->assertJsonMissingPath('data.0.internal_notes')
            ->assertJsonMissingPath('data.0.data.internal_notes');

        $this->getJson("/api/v1/academic-records/{$assignedRecord->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $assignedRecord->id)
            ->assertJsonMissingPath('data.internal_notes')
            ->assertJsonMissingPath('data.data.internal_notes');

        $this->getJson("/api/v1/academic-records/{$otherRecord->id}")
            ->assertForbidden();

        $this->postJson('/api/v1/academic-records', [
            'student_id' => $this->student->id,
            'record_type' => AcademicRecord::TYPE_NOTE,
            'title' => 'Teacher attempt',
        ])
            ->assertForbidden();
    }

    public function test_student_can_view_only_own_records_when_student_visibility_is_enabled(): void
    {
        $record = $this->createAcademicRecord([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
        ]);
        $otherRecord = $this->createAcademicRecord([
            'student_id' => $this->otherStudent->id,
            'teacher_id' => $this->otherTeacher->id,
        ]);

        Sanctum::actingAs($this->student);

        $this->getJson('/api/v1/academic-records?per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $record->id)
            ->assertJsonMissingPath('data.0.internal_notes');

        $this->getJson("/api/v1/academic-records/{$record->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $record->id)
            ->assertJsonMissingPath('data.internal_notes');

        $this->getJson('/api/v1/academic-records?student_id='.$this->otherStudent->id)
            ->assertForbidden();

        $this->getJson("/api/v1/academic-records/{$otherRecord->id}")
            ->assertForbidden();
    }

    public function test_student_record_visibility_can_be_disabled_by_portal_setting(): void
    {
        $record = $this->createAcademicRecord([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
        ]);
        PortalSetting::query()->create([
            'key' => 'academic_records.settings',
            'category' => 'academic_records',
            'value_type' => PortalSetting::TYPE_JSON,
            'value' => [
                'require_teacher_approval' => false,
                'visible_to_students' => false,
                'retention_years' => 7,
                'allowed_record_types' => ['progress', 'attendance', 'assessment', 'note', 'certificate'],
            ],
            'description' => 'Academic record settings.',
            'is_public' => false,
        ]);

        Sanctum::actingAs($this->student);

        $this->getJson('/api/v1/academic-records')
            ->assertForbidden();
        $this->getJson("/api/v1/academic-records/{$record->id}")
            ->assertForbidden();
    }

    public function test_staff_access_depends_on_permissions(): void
    {
        $record = $this->createAcademicRecord([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
        ]);

        Sanctum::actingAs($this->staff);

        $this->getJson('/api/v1/academic-records')
            ->assertForbidden();

        $this->staff->givePermissionTo('academic_records.view');

        $this->getJson('/api/v1/academic-records')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonMissingPath('data.0.internal_notes');

        $this->postJson("/api/v1/academic-records/{$record->id}/archive")
            ->assertForbidden();

        $this->staff->givePermissionTo('academic_records.manage');

        $this->postJson("/api/v1/academic-records/{$record->id}/archive")
            ->assertOk()
            ->assertJsonPath('data.status', AcademicRecord::STATUS_ARCHIVED)
            ->assertJsonPath('data.archived_by', $this->staff->id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createAcademicRecord(array $attributes = []): AcademicRecord
    {
        return AcademicRecord::query()->create([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'record_type' => AcademicRecord::TYPE_PROGRESS,
            'title' => 'Academic progress summary',
            'description' => 'Academic record description.',
            'status' => AcademicRecord::STATUS_ACTIVE,
            'recorded_on' => '2026-06-15',
            'data' => [
                'student_level' => 'B1',
                'attendance_summary' => ['completed' => 10, 'missed' => 0],
                'progress_summary' => 'Improving fluency.',
                'teacher_remarks' => 'Uses new vocabulary confidently.',
                'internal_notes' => 'Internal billing-related note.',
            ],
            'recorded_by' => $this->admin->id,
            ...$attributes,
        ]);
    }
}
