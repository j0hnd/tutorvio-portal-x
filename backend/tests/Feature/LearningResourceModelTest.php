<?php

namespace Tests\Feature;

use App\Http\Resources\LearningResources\LearningResourceResource;
use App\Models\LearningResource;
use App\Models\User;
use App\Services\LearningResourceStorage;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class LearningResourceModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_learning_resource_stores_searchable_metadata(): void
    {
        $creator = User::factory()->create();

        $resource = LearningResource::create([
            'title' => 'Business English worksheet',
            'description' => 'Practice material for workplace vocabulary.',
            'resource_type' => LearningResource::TYPE_WORKSHEET,
            'storage_disk' => 'local',
            'file_path' => 'learning-resources/business-english.pdf',
            'original_filename' => 'business-english.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 123456,
            'preview_metadata' => ['pages' => 4],
            'course' => 'Business English',
            'level' => 'B1',
            'visibility' => LearningResource::VISIBILITY_STUDENT_VISIBLE,
            'created_by' => $creator->id,
        ]);

        $this->assertDatabaseHas('learning_resources', [
            'title' => 'Business English worksheet',
            'resource_type' => LearningResource::TYPE_WORKSHEET,
            'course' => 'Business English',
            'level' => 'B1',
            'visibility' => LearningResource::VISIBILITY_STUDENT_VISIBLE,
            'created_by' => $creator->id,
        ]);
        $this->assertSame(['pages' => 4], $resource->preview_metadata);
        $this->assertTrue($resource->createdBy->is($creator));
        $this->assertTrue(LearningResource::query()->search('workplace vocabulary')->first()->is($resource));
    }

    public function test_learning_resource_storage_uses_configured_private_disk(): void
    {
        Storage::fake('local');
        config([
            'learning_resources.disk' => 'local',
            'learning_resources.directory' => 'learning-resources',
        ]);

        $metadata = app(LearningResourceStorage::class)->store(
            UploadedFile::fake()->create('lesson.pdf', 64, 'application/pdf')
        );

        $this->assertSame('local', $metadata['storage_disk']);
        $this->assertSame('lesson.pdf', $metadata['original_filename']);
        $this->assertSame('application/pdf', $metadata['mime_type']);
        $this->assertStringStartsWith('learning-resources/', $metadata['file_path']);
        Storage::disk('local')->assertExists($metadata['file_path']);
    }

    public function test_resource_serialization_does_not_expose_private_storage_paths(): void
    {
        $resource = LearningResource::create([
            'title' => 'Placement test PDF',
            'resource_type' => LearningResource::TYPE_PDF,
            'storage_disk' => 'local',
            'file_path' => 'learning-resources/private/placement.pdf',
            'original_filename' => 'placement.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 2048,
            'visibility' => LearningResource::VISIBILITY_TEACHER_ONLY,
        ]);

        $payload = (new LearningResourceResource($resource))->resolve();

        $this->assertTrue($payload['has_file']);
        $this->assertNull($payload['url']);
        $this->assertArrayNotHasKey('file_path', $payload);
        $this->assertArrayNotHasKey('storage_disk', $payload);
        $this->assertStringNotContainsString('learning-resources/private/placement.pdf', json_encode($payload));
        $this->assertArrayNotHasKey('file_path', $resource->toArray());
        $this->assertArrayNotHasKey('storage_disk', $resource->toArray());
    }

    public function test_current_version_number_defaults_for_file_resources(): void
    {
        $resource = LearningResource::create([
            'title' => 'Placement test PDF',
            'resource_type' => LearningResource::TYPE_PDF,
            'storage_disk' => 'local',
            'file_path' => 'learning-resources/private/placement.pdf',
            'original_filename' => 'placement.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 2048,
            'visibility' => LearningResource::VISIBILITY_TEACHER_ONLY,
        ]);

        $this->assertSame(1, $resource->currentVersionNumber());
    }

    public function test_visibility_scope_limits_student_access(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);

        $student = User::factory()->create();
        $student->assignRole('student');

        LearningResource::create([
            'title' => 'Student handout',
            'resource_type' => LearningResource::TYPE_DOCUMENT,
            'visibility' => LearningResource::VISIBILITY_STUDENT_VISIBLE,
        ]);
        LearningResource::create([
            'title' => 'Teacher guide',
            'resource_type' => LearningResource::TYPE_DOCUMENT,
            'visibility' => LearningResource::VISIBILITY_TEACHER_ONLY,
        ]);
        LearningResource::create([
            'title' => 'Admin checklist',
            'resource_type' => LearningResource::TYPE_DOCUMENT,
            'visibility' => LearningResource::VISIBILITY_ADMIN_ONLY,
        ]);

        $visibleTitles = LearningResource::query()
            ->visibleTo($student)
            ->pluck('title')
            ->all();

        $this->assertSame(['Student handout'], $visibleTitles);
    }
}
