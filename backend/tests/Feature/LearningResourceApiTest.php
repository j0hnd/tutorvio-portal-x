<?php

namespace Tests\Feature;

use App\Models\LearningResource;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
            ->assertJsonPath('data.preview_metadata', null);

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
            ->assertJsonPath('data.visibility', LearningResource::VISIBILITY_ADMIN_ONLY);

        $this->deleteJson('/api/v1/learning-resources/'.$resource->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('learning_resources', [
            'id' => $resource->id,
        ]);
        Storage::disk('local')->assertMissing('learning-resources/teacher-guide.docx');
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
}
