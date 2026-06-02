<?php

namespace Tests\Feature;

use App\Models\CourseProgram;
use App\Models\CourseProgramStudentAssignment;
use App\Models\CourseType;
use App\Models\LearningResource;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CourseCatalogApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->admin->assignRole('admin');

        Sanctum::actingAs($this->admin);
    }

    public function test_admin_can_manage_course_types(): void
    {
        $response = $this->postJson('/api/v1/course-types', [
            'name' => 'Academic English',
            'description' => 'Academic writing and seminar skills.',
            'sort_order' => 8,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'Academic English')
            ->assertJsonPath('data.slug', 'academic-english')
            ->assertJsonPath('data.sort_order', 8);

        $courseTypeId = $response->json('data.id');

        $this->patchJson("/api/v1/course-types/{$courseTypeId}", [
            'name' => 'Academic English Plus',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Academic English Plus')
            ->assertJsonPath('data.slug', 'academic-english-plus')
            ->assertJsonPath('data.updated_by', $this->admin->id);

        $this->getJson('/api/v1/course-types')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Academic English Plus');

        $this->postJson("/api/v1/course-types/{$courseTypeId}/archive")
            ->assertOk()
            ->assertJsonPath('data.is_archived', true)
            ->assertJsonPath('data.archived_by', $this->admin->id);

        $this->getJson('/api/v1/course-types')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/course-types?only_archived=1')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Academic English Plus');
    }

    public function test_admin_archiving_course_type_archives_its_course_programs(): void
    {
        $courseType = CourseType::factory()->create(['name' => 'Legacy English']);
        $program = CourseProgram::factory()->create([
            'course_type_id' => $courseType->id,
            'title' => 'Legacy Foundation',
        ]);

        $this->postJson("/api/v1/course-types/{$courseType->public_id}/archive")
            ->assertOk()
            ->assertJsonPath('data.is_archived', true)
            ->assertJsonPath('data.archived_by', $this->admin->id);

        $this->assertDatabaseHas('course_programs', [
            'id' => $program->id,
            'is_archived' => true,
            'archived_by' => $this->admin->id,
        ]);

        $this->getJson('/api/v1/course-types')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/course-programs')->assertJsonCount(0, 'data');

        $this->getJson('/api/v1/course-programs?only_archived=1')
            ->assertOk()
            ->assertJsonPath('data.0.id', $program->public_id);
    }

    public function test_admin_can_create_view_update_list_and_archive_course_programs(): void
    {
        $courseType = CourseType::factory()->create(['name' => 'General English']);
        $resource = LearningResource::create([
            'title' => 'Placement worksheet',
            'resource_type' => LearningResource::TYPE_WORKSHEET,
            'course' => 'General English',
            'level' => 'A2',
            'visibility' => LearningResource::VISIBILITY_TEACHER_ONLY,
            'created_by' => $this->admin->id,
        ]);
        $slides = LearningResource::create([
            'title' => 'Foundation slides',
            'resource_type' => LearningResource::TYPE_SLIDE,
            'course' => 'General English',
            'level' => 'A1',
            'visibility' => LearningResource::VISIBILITY_TEACHER_ONLY,
            'created_by' => $this->admin->id,
        ]);

        $createResponse = $this->postJson('/api/v1/course-programs', [
            'course_type_id' => $courseType->id,
            'title' => 'General English Foundation',
            'description' => 'Build practical grammar, vocabulary, listening, and speaking confidence.',
            'placement_level' => 'A1-A2',
            'number_of_sessions' => 12,
            'lesson_structure' => [
                'session_length_minutes' => 50,
                'delivery_mode' => 'one_to_one',
                'components' => ['warm_up', 'target_language', 'feedback'],
            ],
            'milestones' => [
                ['session' => 1, 'goal' => 'Placement review'],
                ['session' => 12, 'goal' => 'Final review'],
            ],
            'learning_resource_ids' => [$resource->id, $slides->id],
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.title', 'General English Foundation')
            ->assertJsonPath('data.name', 'General English Foundation')
            ->assertJsonPath('data.course_type.name', 'General English')
            ->assertJsonPath('data.lesson_structure.components.1', 'target_language')
            ->assertJsonPath('data.milestones.1.goal', 'Final review')
            ->assertJsonPath('data.learning_resources.0.id', $resource->public_id)
            ->assertJsonPath('data.learning_resources.1.id', $slides->public_id)
            ->assertJsonPath('data.learning_resources.0.course_attachment.attached_by', $this->admin->id)
            ->assertJsonPath('data.created_by', $this->admin->id);

        $programId = $createResponse->json('data.id');

        $this->getJson("/api/v1/course-programs/{$programId}")
            ->assertOk()
            ->assertJsonPath('data.id', $programId)
            ->assertJsonPath('data.learning_resources.0.title', 'Placement worksheet');

        $this->patchJson("/api/v1/course-programs/{$programId}", [
            'title' => 'General English Foundation Plus',
            'number_of_sessions' => 14,
            'learning_resource_ids' => [],
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'General English Foundation Plus')
            ->assertJsonPath('data.slug', 'general-english-foundation-plus')
            ->assertJsonPath('data.number_of_sessions', 14)
            ->assertJsonPath('data.updated_by', $this->admin->id)
            ->assertJsonCount(0, 'data.learning_resources');

        $this->getJson('/api/v1/course-programs?search=foundation')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'General English Foundation Plus');

        $this->postJson("/api/v1/course-programs/{$programId}/archive")
            ->assertOk()
            ->assertJsonPath('data.is_archived', true)
            ->assertJsonPath('data.archived_by', $this->admin->id);

        $this->getJson('/api/v1/course-programs')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/course-programs?only_archived=1')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'General English Foundation Plus');
    }

    public function test_admin_can_attach_and_remove_individual_course_program_resources(): void
    {
        $courseType = CourseType::factory()->create();
        $program = CourseProgram::factory()->create(['course_type_id' => $courseType->id]);
        $worksheet = LearningResource::create([
            'title' => 'Unit worksheet',
            'resource_type' => LearningResource::TYPE_WORKSHEET,
            'visibility' => LearningResource::VISIBILITY_TEACHER_ONLY,
            'created_by' => $this->admin->id,
        ]);
        $document = LearningResource::create([
            'title' => 'Teacher notes',
            'resource_type' => LearningResource::TYPE_DOCUMENT,
            'visibility' => LearningResource::VISIBILITY_ADMIN_ONLY,
            'created_by' => $this->admin->id,
        ]);

        $this->postJson("/api/v1/course-programs/{$program->public_id}/learning-resources", [
            'learning_resource_ids' => [$worksheet->id, $document->id],
        ])
            ->assertOk()
            ->assertJsonCount(2, 'data.learning_resources')
            ->assertJsonPath('data.learning_resources.0.course_attachment.attached_by', $this->admin->id);

        $this->assertDatabaseHas('course_program_learning_resource', [
            'course_program_id' => $program->id,
            'learning_resource_id' => $worksheet->id,
            'attached_by' => $this->admin->id,
        ]);
        $this->assertDatabaseHas('course_program_learning_resource', [
            'course_program_id' => $program->id,
            'learning_resource_id' => $document->id,
            'attached_by' => $this->admin->id,
        ]);

        $this->deleteJson("/api/v1/course-programs/{$program->public_id}/learning-resources/{$worksheet->public_id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.learning_resources')
            ->assertJsonPath('data.learning_resources.0.id', $document->public_id);

        $this->assertDatabaseMissing('course_program_learning_resource', [
            'course_program_id' => $program->id,
            'learning_resource_id' => $worksheet->id,
        ]);
    }

    public function test_admin_can_assign_list_view_and_remove_students_from_course_programs(): void
    {
        $courseType = CourseType::factory()->create();
        $program = CourseProgram::factory()->create(['course_type_id' => $courseType->id]);
        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole('student');
        $secondStudent = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $secondStudent->assignRole('student');

        $this->postJson("/api/v1/course-programs/{$program->public_id}/students", [
            'student_ids' => [$student->id, $secondStudent->id],
            'start_date' => '2026-06-01',
            'notes' => 'Initial placement completed.',
        ])
            ->assertCreated()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.course_program_id', $program->public_id)
            ->assertJsonPath('data.0.assigned_by', $this->admin->id)
            ->assertJsonPath('data.0.status', 'active')
            ->assertJsonPath('data.0.start_date', '2026-06-01T00:00:00.000000Z')
            ->assertJsonPath('data.0.notes', 'Initial placement completed.');

        $this->assertDatabaseHas('course_program_student_assignments', [
            'course_program_id' => $program->id,
            'student_id' => $student->id,
            'assigned_by' => $this->admin->id,
            'status' => 'active',
            'start_date' => '2026-06-01 00:00:00',
            'notes' => 'Initial placement completed.',
        ]);

        $this->getJson("/api/v1/course-programs/{$program->public_id}/students")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.student.status', User::STATUS_ACTIVE);

        $this->getJson("/api/v1/students/{$student->public_id}/course-programs")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.course_program.id', $program->public_id)
            ->assertJsonPath('data.0.course_program.course_type.id', $courseType->public_id);

        $this->deleteJson("/api/v1/course-programs/{$program->public_id}/students/{$student->public_id}")
            ->assertNoContent();

        $this->assertDatabaseHas('course_program_student_assignments', [
            'course_program_id' => $program->id,
            'student_id' => $student->id,
            'status' => 'removed',
        ]);

        $this->getJson("/api/v1/course-programs/{$program->public_id}/students")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.student_id', $secondStudent->public_id);
    }

    public function test_course_program_student_assignment_prevents_duplicate_active_assignment_but_allows_multiple_courses(): void
    {
        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole('student');
        $firstProgram = CourseProgram::factory()->create();
        $secondProgram = CourseProgram::factory()->create();

        $this->postJson("/api/v1/course-programs/{$firstProgram->public_id}/students", [
            'student_ids' => [$student->id],
        ])->assertCreated();

        $this->postJson("/api/v1/course-programs/{$firstProgram->public_id}/students", [
            'student_ids' => [$student->id],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('student_ids');

        $this->postJson("/api/v1/course-programs/{$secondProgram->public_id}/students", [
            'student_ids' => [$student->id],
        ])->assertCreated();

        $this->getJson("/api/v1/students/{$student->public_id}/course-programs")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_course_program_assignment_requires_student_role_and_admin_access(): void
    {
        $program = CourseProgram::factory()->create();
        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $teacher->assignRole('teacher');

        $this->postJson("/api/v1/course-programs/{$program->public_id}/students", [
            'student_ids' => [$teacher->id],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('student_ids');

        Sanctum::actingAs($teacher);

        $this->postJson("/api/v1/course-programs/{$program->public_id}/students", [
            'student_ids' => [$teacher->id],
        ])->assertForbidden();

        $this->getJson("/api/v1/course-programs/{$program->public_id}/students")
            ->assertForbidden();
    }

    public function test_teacher_can_view_only_course_programs_assigned_to_their_students(): void
    {
        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $teacher->assignRole('teacher');
        $otherTeacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $otherTeacher->assignRole('teacher');
        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole('student');
        $student->studentProfile()->create(['assigned_teacher_id' => $teacher->id]);
        $otherStudent = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $otherStudent->assignRole('student');
        $otherStudent->studentProfile()->create(['assigned_teacher_id' => $otherTeacher->id]);

        $visibleProgram = CourseProgram::factory()->create(['title' => 'Visible Teacher Program']);
        $hiddenProgram = CourseProgram::factory()->create(['title' => 'Hidden Teacher Program']);
        $archivedProgram = CourseProgram::factory()->archived($this->admin)->create(['title' => 'Archived Teacher Program']);

        $this->assignCourseProgram($visibleProgram, $student);
        $this->assignCourseProgram($hiddenProgram, $otherStudent);
        $this->assignCourseProgram($archivedProgram, $student);

        Sanctum::actingAs($teacher);

        $this->getJson('/api/v1/course-programs?include_archived=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visibleProgram->public_id);

        $this->getJson("/api/v1/course-programs/{$visibleProgram->public_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $visibleProgram->public_id);

        $this->getJson("/api/v1/course-programs/{$hiddenProgram->public_id}")
            ->assertForbidden();

        $this->getJson("/api/v1/course-programs/{$archivedProgram->public_id}")
            ->assertNotFound();
    }

    public function test_student_can_view_only_their_assigned_course_programs_without_admin_fields(): void
    {
        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole('student');
        $otherStudent = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $otherStudent->assignRole('student');
        $visibleProgram = CourseProgram::factory()->create(['title' => 'Student Visible Program']);
        $hiddenProgram = CourseProgram::factory()->create(['title' => 'Student Hidden Program']);
        $archivedProgram = CourseProgram::factory()->archived($this->admin)->create(['title' => 'Student Archived Program']);

        $this->assignCourseProgram($visibleProgram, $student);
        $this->assignCourseProgram($hiddenProgram, $otherStudent);
        $this->assignCourseProgram($archivedProgram, $student);

        Sanctum::actingAs($student);

        $this->getJson('/api/v1/course-programs?include_archived=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visibleProgram->public_id)
            ->assertJsonMissingPath('data.0.is_archived')
            ->assertJsonMissingPath('data.0.archived_by')
            ->assertJsonMissingPath('data.0.created_by')
            ->assertJsonMissingPath('data.0.updated_by');

        $this->getJson("/api/v1/course-programs/{$visibleProgram->public_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $visibleProgram->public_id)
            ->assertJsonMissingPath('data.created_by');

        $this->getJson("/api/v1/course-programs/{$hiddenProgram->public_id}")
            ->assertForbidden();

        $this->getJson("/api/v1/students/{$student->public_id}/course-programs")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.course_program.id', $visibleProgram->public_id);

        $this->getJson("/api/v1/students/{$otherStudent->public_id}/course-programs")
            ->assertForbidden();
    }

    public function test_teacher_can_view_course_programs_for_their_assigned_student_only(): void
    {
        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $teacher->assignRole('teacher');
        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole('student');
        $student->studentProfile()->create(['assigned_teacher_id' => $teacher->id]);
        $otherStudent = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $otherStudent->assignRole('student');

        $program = CourseProgram::factory()->create();
        $this->assignCourseProgram($program, $student);

        Sanctum::actingAs($teacher);

        $this->getJson("/api/v1/students/{$student->public_id}/course-programs")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.course_program.id', $program->public_id);

        $this->getJson("/api/v1/students/{$otherStudent->public_id}/course-programs")
            ->assertForbidden();
    }

    public function test_staff_course_program_access_depends_on_permissions(): void
    {
        $program = CourseProgram::factory()->create();
        $courseType = CourseType::factory()->create();
        $staff = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('staff');

        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/course-programs')
            ->assertForbidden();

        $staff->givePermissionTo('course_programs.view');

        $this->getJson('/api/v1/course-programs')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $program->public_id);

        $this->patchJson("/api/v1/course-programs/{$program->public_id}", [
            'title' => 'Staff Blocked Update',
        ])->assertForbidden();

        $this->postJson('/api/v1/course-programs', [
            'course_type_id' => $courseType->id,
            'title' => 'Staff Blocked Create',
            'number_of_sessions' => 8,
            'lesson_structure' => ['components' => ['feedback']],
        ])->assertForbidden();

        $this->postJson("/api/v1/course-programs/{$program->public_id}/archive")
            ->assertForbidden();

        $staff->givePermissionTo('course_programs.update');

        $this->patchJson("/api/v1/course-programs/{$program->public_id}", [
            'title' => 'Staff Allowed Update',
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Staff Allowed Update');

        $staff->givePermissionTo('course_programs.create');

        $createResponse = $this->postJson('/api/v1/course-programs', [
            'course_type_id' => $courseType->id,
            'title' => 'Staff Allowed Create',
            'number_of_sessions' => 8,
            'lesson_structure' => ['components' => ['feedback']],
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Staff Allowed Create');

        $staffCreatedProgramId = $createResponse->json('data.id');

        $staff->givePermissionTo('course_programs.delete');

        $this->postJson("/api/v1/course-programs/{$staffCreatedProgramId}/archive")
            ->assertOk()
            ->assertJsonPath('data.is_archived', true)
            ->assertJsonPath('data.archived_by', $staff->id);
    }

    public function test_course_program_validation_rejects_missing_fields_and_duplicate_active_titles(): void
    {
        $courseType = CourseType::factory()->create();
        CourseProgram::factory()->create([
            'course_type_id' => $courseType->id,
            'title' => 'Business English Accelerator',
        ]);
        CourseProgram::factory()->archived()->create([
            'course_type_id' => $courseType->id,
            'title' => 'Archived Reusable Title',
        ]);

        $this->postJson('/api/v1/course-programs', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['course_type_id', 'title', 'number_of_sessions', 'lesson_structure']);

        $this->postJson('/api/v1/course-programs', [
            'course_type_id' => $courseType->id,
            'title' => 'Business English Accelerator',
            'number_of_sessions' => 10,
            'lesson_structure' => ['components' => ['feedback']],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');

        $this->postJson('/api/v1/course-programs', [
            'course_type_id' => $courseType->id,
            'title' => 'Archived Reusable Title',
            'number_of_sessions' => 10,
            'lesson_structure' => ['components' => ['feedback']],
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Archived Reusable Title');
    }

    public function test_non_admin_users_cannot_create_update_or_archive_courses(): void
    {
        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $teacher->assignRole('teacher');
        $courseType = CourseType::factory()->create();
        $program = CourseProgram::factory()->create(['course_type_id' => $courseType->id]);

        Sanctum::actingAs($teacher);

        $this->postJson('/api/v1/course-programs', [
            'course_type_id' => $courseType->id,
            'title' => 'Blocked Program',
            'number_of_sessions' => 8,
            'lesson_structure' => ['components' => ['feedback']],
        ])->assertForbidden();

        $this->patchJson("/api/v1/course-programs/{$program->public_id}", [
            'title' => 'Blocked Update',
        ])->assertForbidden();

        $this->postJson("/api/v1/course-programs/{$program->public_id}/archive")
            ->assertForbidden();

        $this->postJson("/api/v1/course-programs/{$program->public_id}/learning-resources", [
            'learning_resource_ids' => [
                LearningResource::create([
                    'title' => 'Blocked attachment',
                    'resource_type' => LearningResource::TYPE_WORKSHEET,
                    'visibility' => LearningResource::VISIBILITY_TEACHER_ONLY,
                    'created_by' => $this->admin->id,
                ])->id,
            ],
        ])->assertForbidden();

        $this->postJson('/api/v1/course-types', [
            'name' => 'Blocked Type',
        ])->assertForbidden();
    }

    private function assignCourseProgram(CourseProgram $program, User $student): CourseProgramStudentAssignment
    {
        return CourseProgramStudentAssignment::create([
            'course_program_id' => $program->id,
            'student_id' => $student->id,
            'assigned_by' => $this->admin->id,
            'assigned_at' => now(),
            'status' => CourseProgramStudentAssignment::STATUS_ACTIVE,
        ]);
    }
}
