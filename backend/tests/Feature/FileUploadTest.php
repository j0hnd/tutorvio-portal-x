<?php

namespace Tests\Feature;

use App\Models\LearningResource;
use App\Models\User;
use App\Services\LearningResourceStorage;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FileUploadTest extends TestCase
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

    public function test_upload_validates_file_mime_extension_and_size(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/learning-resources/files', [
            'title' => 'Disguised executable',
            'resource_type' => LearningResource::TYPE_PDF,
            'file' => UploadedFile::fake()->create('tool.exe', 64, 'application/pdf'),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->postJson('/api/v1/learning-resources/files', [
            'title' => 'Unsafe MIME',
            'resource_type' => LearningResource::TYPE_PDF,
            'file' => UploadedFile::fake()->create('tool.pdf', 64, 'application/x-msdownload'),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->postJson('/api/v1/learning-resources/files', [
            'title' => 'Oversized file',
            'resource_type' => LearningResource::TYPE_PDF,
            'file' => UploadedFile::fake()->create('large.pdf', 2048, 'application/pdf'),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_upload_stores_file_on_protected_storage_without_exposing_raw_path(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/learning-resources/files', [
            'title' => 'Private worksheet',
            'resource_type' => LearningResource::TYPE_WORKSHEET,
            'file' => UploadedFile::fake()->create('lesson"plan.pdf', 128, 'application/pdf'),
            'visibility' => LearningResource::VISIBILITY_STUDENT_VISIBLE,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.original_filename', 'lesson_plan.pdf')
            ->assertJsonMissingPath('data.file_path')
            ->assertJsonMissingPath('data.storage_disk');

        $resource = LearningResource::firstOrFail();

        $this->assertSame('local', $resource->storage_disk);
        $this->assertStringStartsWith('learning-resources/', $resource->file_path);
        $this->assertStringNotContainsString('lesson_plan.pdf', $resource->file_path);
        Storage::disk('local')->assertExists($resource->file_path);
    }

    public function test_learning_resource_storage_rejects_public_disk_configuration(): void
    {
        config(['learning_resources.disk' => 'public']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Learning resource uploads must use protected storage.');

        app(LearningResourceStorage::class)->store(
            UploadedFile::fake()->create('worksheet.pdf', 16, 'application/pdf')
        );
    }
}
