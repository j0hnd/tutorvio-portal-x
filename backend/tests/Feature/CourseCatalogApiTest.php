<?php

namespace Tests\Feature;

use App\Models\CourseProgram;
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
            'learning_resource_ids' => [$resource->id],
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.title', 'General English Foundation')
            ->assertJsonPath('data.name', 'General English Foundation')
            ->assertJsonPath('data.course_type.name', 'General English')
            ->assertJsonPath('data.lesson_structure.components.1', 'target_language')
            ->assertJsonPath('data.milestones.1.goal', 'Final review')
            ->assertJsonPath('data.learning_resources.0.id', $resource->id)
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

        $this->patchJson("/api/v1/course-programs/{$program->id}", [
            'title' => 'Blocked Update',
        ])->assertForbidden();

        $this->postJson("/api/v1/course-programs/{$program->id}/archive")
            ->assertForbidden();

        $this->postJson('/api/v1/course-types', [
            'name' => 'Blocked Type',
        ])->assertForbidden();
    }
}
