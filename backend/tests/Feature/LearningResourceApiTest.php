<?php

namespace Tests\Feature;

use App\Models\LearningResource;
use App\Models\Lesson;
use App\Models\LessonNote;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class LearningResourceApiTest extends TestCase
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

        config([
            'learning_resources.disk' => 'local',
            'learning_resources.directory' => 'learning-resources',
            'learning_resources.max_upload_kilobytes' => 1024,
        ]);

        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_can_upload_file_resource(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/learning-resources/files', [
            'title' => 'Placement worksheet',
            'description' => 'Initial placement exercises.',
            'resource_type' => LearningResource::TYPE_WORKSHEET,
            'file' => UploadedFile::fake()->create('placement.pdf', 256, 'application/pdf'),
            'course' => 'General English',
            'level' => 'A2',
            'visibility' => LearningResource::VISIBILITY_STUDENT_VISIBLE,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.title', 'Placement worksheet')
            ->assertJsonPath('data.resource_type', LearningResource::TYPE_WORKSHEET)
            ->assertJsonPath('data.original_filename', 'placement.pdf')
            ->assertJsonPath('data.mime_type', 'application/pdf')
            ->assertJsonPath('data.file_size', 262144)
            ->assertJsonPath('data.preview_metadata.filename', 'placement.pdf')
            ->assertJsonPath('data.preview_metadata.mime_type', 'application/pdf')
            ->assertJsonPath('data.preview_metadata.size', 262144)
            ->assertJsonPath('data.preview_metadata.extension', 'pdf')
            ->assertJsonPath('data.course', 'General English')
            ->assertJsonPath('data.level', 'A2')
            ->assertJsonPath('data.grouping.course.name', 'General English')
            ->assertJsonPath('data.grouping.level.name', 'A2')
            ->assertJsonPath('data.grouping.is_generic', false)
            ->assertJsonPath('data.has_file', true)
            ->assertJsonPath('data.url', null)
            ->assertJsonMissingPath('data.file_path')
            ->assertJsonMissingPath('data.storage_disk');

        $resource = LearningResource::firstOrFail();

        Storage::disk('local')->assertExists($resource->file_path);
        $this->assertSame($this->admin->id, $resource->created_by);
    }

    public function test_file_upload_validates_required_fields_allowed_mime_and_size(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/learning-resources/files', [
            'file' => UploadedFile::fake()->create('tool.exe', 64, 'application/x-msdownload'),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'resource_type', 'file']);

        $this->postJson('/api/v1/learning-resources/files', [
            'title' => 'Oversized PDF',
            'resource_type' => LearningResource::TYPE_PDF,
            'file' => UploadedFile::fake()->create('large.pdf', 2048, 'application/pdf'),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_admin_can_create_link_resource_without_file_metadata(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/learning-resources/links', [
            'title' => 'Pronunciation practice',
            'description' => 'External pronunciation drills.',
            'resource_type' => LearningResource::TYPE_LINK,
            'url' => 'https://example.com/pronunciation',
            'visibility' => LearningResource::VISIBILITY_TEACHER_ONLY,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.resource_type', LearningResource::TYPE_LINK)
            ->assertJsonPath('data.url', 'https://example.com/pronunciation')
            ->assertJsonPath('data.has_file', false)
            ->assertJsonPath('data.original_filename', null)
            ->assertJsonPath('data.mime_type', null)
            ->assertJsonPath('data.file_size', null)
            ->assertJsonPath('data.preview_metadata', null)
            ->assertJsonPath('data.grouping.course', null)
            ->assertJsonPath('data.grouping.level', null)
            ->assertJsonPath('data.grouping.is_generic', true);

        $this->assertDatabaseHas('learning_resources', [
            'title' => 'Pronunciation practice',
            'resource_type' => LearningResource::TYPE_LINK,
            'url' => 'https://example.com/pronunciation',
            'file_path' => null,
        ]);
    }

    public function test_link_resource_requires_valid_url_and_link_type(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/learning-resources/links', [
            'title' => 'Bad link',
            'resource_type' => LearningResource::TYPE_DOCUMENT,
            'url' => 'not-a-url',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['resource_type', 'url']);
    }

    public function test_admin_can_view_update_and_delete_resource(): void
    {
        Sanctum::actingAs($this->admin);

        $resource = LearningResource::create([
            'title' => 'Teacher guide',
            'resource_type' => LearningResource::TYPE_DOCUMENT,
            'storage_disk' => 'local',
            'file_path' => 'learning-resources/teacher-guide.docx',
            'original_filename' => 'teacher-guide.docx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'file_size' => 512,
            'visibility' => LearningResource::VISIBILITY_TEACHER_ONLY,
            'created_by' => $this->admin->id,
        ]);
        Storage::disk('local')->put($resource->file_path, 'document');

        $this->getJson('/api/v1/learning-resources/'.$resource->id)
            ->assertOk()
            ->assertJsonPath('data.id', $resource->id)
            ->assertJsonPath('data.title', 'Teacher guide');

        $this->patchJson('/api/v1/learning-resources/'.$resource->id, [
            'title' => 'Updated teacher guide',
            'course' => 'Business English',
            'level' => 'B2',
            'visibility' => LearningResource::VISIBILITY_ADMIN_ONLY,
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated teacher guide')
            ->assertJsonPath('data.course', 'Business English')
            ->assertJsonPath('data.level', 'B2')
            ->assertJsonPath('data.grouping.course.name', 'Business English')
            ->assertJsonPath('data.grouping.level.name', 'B2')
            ->assertJsonPath('data.visibility', LearningResource::VISIBILITY_ADMIN_ONLY);

        $this->deleteJson('/api/v1/learning-resources/'.$resource->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('learning_resources', [
            'id' => $resource->id,
        ]);
        Storage::disk('local')->assertMissing('learning-resources/teacher-guide.docx');
    }

    public function test_admin_can_filter_resources_by_group_type_and_visibility(): void
    {
        Sanctum::actingAs($this->admin);

        $matching = LearningResource::create([
            'title' => 'A2 grammar worksheet',
            'resource_type' => LearningResource::TYPE_WORKSHEET,
            'course' => 'General English',
            'level' => 'A2',
            'visibility' => LearningResource::VISIBILITY_STUDENT_VISIBLE,
        ]);
        LearningResource::create([
            'title' => 'B1 grammar worksheet',
            'resource_type' => LearningResource::TYPE_WORKSHEET,
            'course' => 'General English',
            'level' => 'B1',
            'visibility' => LearningResource::VISIBILITY_STUDENT_VISIBLE,
        ]);
        LearningResource::create([
            'title' => 'A2 teacher guide',
            'resource_type' => LearningResource::TYPE_DOCUMENT,
            'course' => 'General English',
            'level' => 'A2',
            'visibility' => LearningResource::VISIBILITY_TEACHER_ONLY,
        ]);
        LearningResource::create([
            'title' => 'Generic public worksheet',
            'resource_type' => LearningResource::TYPE_WORKSHEET,
            'visibility' => LearningResource::VISIBILITY_PUBLIC,
        ]);

        $this->getJson('/api/v1/learning-resources?course=General%20English&level=A2&resource_type=worksheet&visibility=student-visible')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matching->id)
            ->assertJsonPath('data.0.grouping.course.name', 'General English')
            ->assertJsonPath('data.0.grouping.level.name', 'A2')
            ->assertJsonPath('data.0.visibility', LearningResource::VISIBILITY_STUDENT_VISIBLE);
    }

    public function test_student_can_only_view_visible_resources(): void
    {
        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole('student');
        Sanctum::actingAs($student);

        $visible = LearningResource::create([
            'title' => 'Student handout',
            'resource_type' => LearningResource::TYPE_DOCUMENT,
            'visibility' => LearningResource::VISIBILITY_STUDENT_VISIBLE,
        ]);
        $hidden = LearningResource::create([
            'title' => 'Admin checklist',
            'resource_type' => LearningResource::TYPE_DOCUMENT,
            'visibility' => LearningResource::VISIBILITY_ADMIN_ONLY,
        ]);

        $this->getJson('/api/v1/learning-resources/'.$visible->id)
            ->assertOk()
            ->assertJsonPath('data.title', 'Student handout');

        $this->getJson('/api/v1/learning-resources/'.$hidden->id)
            ->assertForbidden();
    }

    public function test_admin_can_assign_and_unassign_resource_to_student(): void
    {
        Carbon::setTestNow('2026-06-01 12:00:00');
        Sanctum::actingAs($this->admin);

        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole('student');
        StudentProfile::create(['user_id' => $student->id]);
        $resource = $this->createResource();

        $this->postJson('/api/v1/learning-resources/'.$resource->id.'/students', [
            'student_id' => $student->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.id', $resource->id)
            ->assertJsonPath('data.assignment.assigned_by', $this->admin->id)
            ->assertJsonPath('data.assignment.assigned_at', '2026-06-01T12:00:00.000000Z');

        $this->assertDatabaseHas('learning_resource_student', [
            'learning_resource_id' => $resource->id,
            'student_id' => $student->id,
            'assigned_by' => $this->admin->id,
            'assigned_at' => '2026-06-01 12:00:00',
        ]);

        $this->postJson('/api/v1/learning-resources/'.$resource->id.'/students', [
            'student_id' => $student->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('student_id');

        $this->getJson('/api/v1/users/'.$student->id.'/profile')
            ->assertOk()
            ->assertJsonPath('data.student_profile.learning_resources.0.id', $resource->id)
            ->assertJsonPath('data.student_profile.learning_resources.0.assignment.assigned_by', $this->admin->id);

        $this->deleteJson('/api/v1/learning-resources/'.$resource->id.'/students/'.$student->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('learning_resource_student', [
            'learning_resource_id' => $resource->id,
            'student_id' => $student->id,
        ]);
    }

    public function test_admin_can_assign_and_unassign_resource_to_lesson(): void
    {
        Carbon::setTestNow('2026-06-01 12:30:00');
        Sanctum::actingAs($this->admin);

        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole('student');
        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $teacher->assignRole('teacher');
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
            'topics_covered' => 'Introductions.',
            'submitted_at' => '2026-06-01 10:10:00',
        ]);
        $resource = $this->createResource();

        $this->postJson('/api/v1/learning-resources/'.$resource->id.'/lessons', [
            'lesson_id' => $lesson->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.id', $resource->id)
            ->assertJsonPath('data.assignment.assigned_by', $this->admin->id)
            ->assertJsonPath('data.assignment.assigned_at', '2026-06-01T12:30:00.000000Z');

        $this->postJson('/api/v1/learning-resources/'.$resource->id.'/lessons', [
            'lesson_id' => $lesson->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lesson_id');

        $this->getJson('/api/v1/lessons/'.$lesson->id.'/lesson-notes')
            ->assertOk()
            ->assertJsonPath('data.0.id', $lessonNote->id)
            ->assertJsonPath('data.0.lesson.learning_resources.0.id', $resource->id)
            ->assertJsonPath('data.0.lesson.learning_resources.0.assignment.assigned_by', $this->admin->id);

        $this->deleteJson('/api/v1/learning-resources/'.$resource->id.'/lessons/'.$lesson->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('learning_resource_lesson', [
            'learning_resource_id' => $resource->id,
            'lesson_id' => $lesson->id,
        ]);
    }

    public function test_resource_assignment_validates_referenced_records_and_student_role(): void
    {
        Sanctum::actingAs($this->admin);

        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $teacher->assignRole('teacher');
        $resource = $this->createResource();

        $this->postJson('/api/v1/learning-resources/'.$resource->id.'/students', [
            'student_id' => 999999,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('student_id');

        $this->postJson('/api/v1/learning-resources/'.$resource->id.'/students', [
            'student_id' => $teacher->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('student_id');

        $this->postJson('/api/v1/learning-resources/'.$resource->id.'/lessons', [
            'lesson_id' => 999999,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lesson_id');
    }

    private function createResource(array $overrides = []): LearningResource
    {
        return LearningResource::create([
            'title' => 'Assigned worksheet',
            'resource_type' => LearningResource::TYPE_WORKSHEET,
            'visibility' => LearningResource::VISIBILITY_STUDENT_VISIBLE,
            'created_by' => $this->admin->id,
            ...$overrides,
        ]);
    }
}
