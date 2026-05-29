<?php

namespace Tests\Feature;

use App\Models\LearningResource;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FileDownloadTest extends TestCase
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

        config(['learning_resources.disk' => 'local']);
        Storage::fake('local');
    }

    public function test_download_requires_authentication_and_authorization(): void
    {
        $student = $this->userWithRole('student');
        $otherStudent = $this->userWithRole('student');
        $ownResource = $this->storedResource('own-worksheet.pdf');
        $otherResource = $this->storedResource('other-worksheet.pdf');

        $ownResource->assignedStudents()->attach($student->id, [
            'assigned_by' => $this->admin->id,
            'assigned_at' => now(),
        ]);
        $otherResource->assignedStudents()->attach($otherStudent->id, [
            'assigned_by' => $this->admin->id,
            'assigned_at' => now(),
        ]);

        $this->getJson("/api/v1/learning-resources/{$ownResource->id}/download")
            ->assertUnauthorized();

        Sanctum::actingAs($student);

        $this->getJson("/api/v1/learning-resources/{$ownResource->id}/download")
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertDownload('own-worksheet.pdf');

        $this->getJson("/api/v1/learning-resources/{$otherResource->id}/download")
            ->assertForbidden();
    }

    public function test_file_download_response_does_not_expose_storage_disk_or_raw_path(): void
    {
        $resource = $this->storedResource('student-visible.pdf');

        Sanctum::actingAs($this->admin);

        $response = $this->getJson("/api/v1/learning-resources/{$resource->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.download.endpoint', url("/api/v1/learning-resources/{$resource->id}/download"))
            ->assertJsonMissingPath('data.file_path')
            ->assertJsonMissingPath('data.storage_disk');

        $this->assertStringNotContainsString($resource->file_path, json_encode($response->json()));
    }

    private function storedResource(string $filename): LearningResource
    {
        $resource = LearningResource::create([
            'title' => $filename,
            'resource_type' => LearningResource::TYPE_WORKSHEET,
            'visibility' => LearningResource::VISIBILITY_STUDENT_VISIBLE,
            'storage_disk' => 'local',
            'file_path' => 'learning-resources/'.uniqid('file-', true).'.pdf',
            'original_filename' => $filename,
            'mime_type' => 'application/pdf',
            'file_size' => 12,
            'created_by' => $this->admin->id,
        ]);

        Storage::disk('local')->put($resource->file_path, 'private document');

        return $resource;
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $user->assignRole($role);

        if ($role === 'student') {
            $user->studentProfile()->create();
        }

        return $user;
    }
}
