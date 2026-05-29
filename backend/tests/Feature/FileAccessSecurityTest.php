<?php

namespace Tests\Feature;

use App\Models\LearningResource;
use App\Models\Lesson;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FileAccessSecurityTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_students_can_only_access_assigned_or_student_visible_files(): void
    {
        Storage::fake('local');

        $student = $this->userWithRole('student');
        $otherStudent = $this->userWithRole('student');
        $openResource = $this->storedResource('open.pdf');
        $ownAssigned = $this->storedResource('own.pdf');
        $otherAssigned = $this->storedResource('other.pdf');
        $teacherOnly = $this->storedResource('teacher-only.pdf', [
            'visibility' => LearningResource::VISIBILITY_TEACHER_ONLY,
        ]);

        $ownAssigned->assignedStudents()->attach($student->id, [
            'assigned_by' => $this->admin->id,
            'assigned_at' => now(),
        ]);
        $otherAssigned->assignedStudents()->attach($otherStudent->id, [
            'assigned_by' => $this->admin->id,
            'assigned_at' => now(),
        ]);

        Sanctum::actingAs($student);

        $this->getJson("/api/v1/learning-resources/{$openResource->id}/download")->assertOk();
        $this->getJson("/api/v1/learning-resources/{$ownAssigned->id}/download")->assertOk();
        $this->getJson("/api/v1/learning-resources/{$otherAssigned->id}/download")->assertForbidden();
        $this->getJson("/api/v1/learning-resources/{$teacherOnly->id}/download")->assertForbidden();
    }

    public function test_teachers_can_only_access_assigned_student_or_class_files_and_teacher_visible_resources(): void
    {
        Storage::fake('local');

        $teacher = $this->userWithRole('teacher');
        $otherTeacher = $this->userWithRole('teacher');
        $student = $this->userWithRole('student');
        $otherStudent = $this->userWithRole('student');
        StudentProfile::where('user_id', $student->id)->update(['assigned_teacher_id' => $teacher->id]);
        StudentProfile::where('user_id', $otherStudent->id)->update(['assigned_teacher_id' => $otherTeacher->id]);

        $ownLesson = $this->lessonFor($student, $teacher);
        $otherLesson = $this->lessonFor($otherStudent, $otherTeacher);
        $teacherVisible = $this->storedResource('teacher-visible.pdf', [
            'visibility' => LearningResource::VISIBILITY_TEACHER_ONLY,
        ]);
        $ownStudentResource = $this->storedResource('own-student.pdf');
        $ownLessonResource = $this->storedResource('own-lesson.pdf');
        $otherStudentResource = $this->storedResource('other-student.pdf');
        $otherLessonResource = $this->storedResource('other-lesson.pdf');
        $adminOnly = $this->storedResource('admin-only.pdf', [
            'visibility' => LearningResource::VISIBILITY_ADMIN_ONLY,
        ]);

        $ownStudentResource->assignedStudents()->attach($student->id, ['assigned_by' => $this->admin->id, 'assigned_at' => now()]);
        $ownLessonResource->assignedLessons()->attach($ownLesson->id, ['assigned_by' => $this->admin->id, 'assigned_at' => now()]);
        $otherStudentResource->assignedStudents()->attach($otherStudent->id, ['assigned_by' => $this->admin->id, 'assigned_at' => now()]);
        $otherLessonResource->assignedLessons()->attach($otherLesson->id, ['assigned_by' => $this->admin->id, 'assigned_at' => now()]);

        Sanctum::actingAs($teacher);

        $this->getJson("/api/v1/learning-resources/{$teacherVisible->id}/download")->assertOk();
        $this->getJson("/api/v1/learning-resources/{$ownStudentResource->id}/download")->assertOk();
        $this->getJson("/api/v1/learning-resources/{$ownLessonResource->id}/download")->assertOk();
        $this->getJson("/api/v1/learning-resources/{$otherStudentResource->id}/download")->assertForbidden();
        $this->getJson("/api/v1/learning-resources/{$otherLessonResource->id}/download")->assertForbidden();
        $this->getJson("/api/v1/learning-resources/{$adminOnly->id}/download")->assertForbidden();
    }

    public function test_temporary_file_urls_are_only_issued_after_authorization(): void
    {
        config([
            'learning_resources.download.strategy' => 'temporary_url',
            'learning_resources.download.temporary_url_ttl_minutes' => 5,
        ]);
        Carbon::setTestNow('2026-05-29 09:00:00');

        $student = $this->userWithRole('student');
        $otherStudent = $this->userWithRole('student');
        $resource = $this->storedResource('cloud.pdf', [
            'storage_disk' => 's3',
            'file_path' => 'learning-resources/private/cloud.pdf',
        ]);
        $resource->assignedStudents()->attach($student->id, [
            'assigned_by' => $this->admin->id,
            'assigned_at' => now(),
        ]);

        $diskMock = Mockery::mock();
        $diskMock->shouldReceive('exists')->once()->andReturnTrue();
        $diskMock->shouldReceive('providesTemporaryUrls')->once()->andReturnTrue();
        $diskMock->shouldReceive('temporaryUrl')->once()->andReturn('https://cdn.example.test/cloud.pdf?signature=secret');
        Storage::shouldReceive('disk')->with('s3')->andReturn($diskMock);

        Sanctum::actingAs($student);

        $this->getJson("/api/v1/learning-resources/{$resource->id}/download")
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertJsonPath('data.download_url', 'https://cdn.example.test/cloud.pdf?signature=secret')
            ->assertJsonPath('data.expires_at', '2026-05-29T09:05:00+00:00')
            ->assertJsonMissingPath('data.file_path')
            ->assertJsonMissingPath('data.storage_disk');

        Sanctum::actingAs($otherStudent);

        $this->getJson("/api/v1/learning-resources/{$resource->id}/download")
            ->assertForbidden();
    }

    public function test_unauthorized_users_cannot_access_protected_file_downloads(): void
    {
        Storage::fake('local');

        $resource = $this->storedResource('protected.pdf');

        $this->getJson("/api/v1/learning-resources/{$resource->id}/download")
            ->assertUnauthorized()
            ->assertJsonMissing(['protected.pdf'])
            ->assertJsonMissing(['private document']);
    }

    public function test_private_file_storage_paths_and_signed_urls_are_not_exposed_in_resource_payloads(): void
    {
        $student = $this->userWithRole('student');
        $resource = $this->storedResource('assigned.pdf', [
            'storage_disk' => 's3',
            'file_path' => 'learning-resources/private/assigned.pdf?signature=storage-secret',
            'preview_metadata' => [
                'private_url' => 'https://cdn.example.test/private/assigned.pdf?signature=preview-secret',
            ],
        ]);
        $resource->assignedStudents()->attach($student->id, [
            'assigned_by' => $this->admin->id,
            'assigned_at' => now(),
        ]);

        Sanctum::actingAs($student);

        $this->getJson("/api/v1/learning-resources/{$resource->id}")
            ->assertOk()
            ->assertJsonPath('data.title', 'assigned.pdf')
            ->assertJsonMissingPath('data.file_path')
            ->assertJsonMissingPath('data.storage_disk')
            ->assertJsonMissingPath('data.preview_metadata')
            ->assertJsonMissing(['storage-secret'])
            ->assertJsonMissing(['preview-secret']);
    }

    public function test_admin_and_staff_download_access_follows_permission_rules(): void
    {
        Storage::fake('local');

        $staff = $this->userWithRole('staff');
        $adminOnly = $this->storedResource('admin-only.pdf', [
            'visibility' => LearningResource::VISIBILITY_ADMIN_ONLY,
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson("/api/v1/learning-resources/{$adminOnly->id}/download")
            ->assertOk();

        Sanctum::actingAs($staff);

        $this->getJson("/api/v1/learning-resources/{$adminOnly->id}/download")
            ->assertForbidden();

        $staff->givePermissionTo('learning_resources.view');

        $this->getJson("/api/v1/learning-resources/{$adminOnly->id}/download")
            ->assertOk();
    }

    private function storedResource(string $filename, array $overrides = []): LearningResource
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
            ...$overrides,
        ]);

        if ($resource->storage_disk === 'local') {
            Storage::disk('local')->put($resource->file_path, 'private document');
        }

        return $resource;
    }

    private function lessonFor(User $student, User $teacher): Lesson
    {
        return Lesson::create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'start_time' => '2026-06-01 09:00:00',
            'end_time' => '2026-06-01 10:00:00',
            'status' => Lesson::STATUS_COMPLETED,
        ]);
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
