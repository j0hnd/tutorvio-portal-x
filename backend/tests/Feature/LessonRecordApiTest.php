<?php

namespace Tests\Feature;

use App\Models\LessonRecord;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class LessonRecordApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create(['status' => User::STATUS_ACTIVE, 'timezone' => 'Asia/Manila']);
        $this->admin->assignRole('admin');

        $this->teacher = User::factory()->create(['status' => User::STATUS_ACTIVE, 'timezone' => 'Asia/Manila']);
        $this->teacher->assignRole('teacher');

        $this->student = User::factory()->create(['status' => User::STATUS_ACTIVE, 'timezone' => 'Asia/Manila']);
        $this->student->assignRole('student');
    }

    public function test_admin_can_create_lesson_record(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/lesson-records', $this->validPayload([
            'lesson_notes' => 'Focused on speaking fluency.',
            'homework_details' => 'Complete unit 4 exercises.',
        ]));

        $response
            ->assertCreated()
            ->assertJsonPath('data.student_id', $this->student->id)
            ->assertJsonPath('data.teacher_id', $this->teacher->id)
            ->assertJsonPath('data.lesson_type', LessonRecord::TYPE_BUSINESS_ENGLISH)
            ->assertJsonPath('data.lesson_status', LessonRecord::STATUS_SCHEDULED)
            ->assertJsonPath('data.is_completed', false);

        $this->assertDatabaseHas('lesson_records', [
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'scheduled_date' => '2026-06-01 00:00:00',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_create_lesson_record_requires_core_fields(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/lesson-records', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'student_id',
                'teacher_id',
                'scheduled_date',
                'start_time',
                'end_time',
                'lesson_type',
                'lesson_status',
            ]);
    }

    public function test_admin_can_list_show_update_and_cancel_lesson_record(): void
    {
        Sanctum::actingAs($this->admin);
        $lessonRecord = $this->createLessonRecord();

        $this->getJson('/api/v1/lesson-records?student_id='.$this->student->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $lessonRecord->id);

        $this->getJson('/api/v1/lesson-records/'.$lessonRecord->id)
            ->assertOk()
            ->assertJsonPath('data.student.id', $this->student->id)
            ->assertJsonPath('data.teacher.id', $this->teacher->id);

        $this->patchJson('/api/v1/lesson-records/'.$lessonRecord->id, [
            'lesson_status' => LessonRecord::STATUS_COMPLETED,
            'is_completed' => true,
            'lesson_notes' => 'Student completed the target lesson.',
        ])
            ->assertOk()
            ->assertJsonPath('data.lesson_status', LessonRecord::STATUS_COMPLETED)
            ->assertJsonPath('data.is_completed', true)
            ->assertJsonPath('data.completed_by', $this->admin->id);

        $this->postJson('/api/v1/lesson-records/'.$lessonRecord->id.'/cancel', [
            'reason' => 'Student requested a later slot.',
        ])
            ->assertOk()
            ->assertJsonPath('data.lesson_status', LessonRecord::STATUS_CANCELLED)
            ->assertJsonPath('data.is_completed', false);
    }

    public function test_teacher_can_only_see_their_own_lesson_records(): void
    {
        $otherTeacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $otherTeacher->assignRole('teacher');

        $ownLessonRecord = $this->createLessonRecord();
        $otherLessonRecord = $this->createLessonRecord(['teacher_id' => $otherTeacher->id]);

        Sanctum::actingAs($this->teacher);

        $this->getJson('/api/v1/lesson-records')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownLessonRecord->id);

        $this->getJson('/api/v1/lesson-records/'.$ownLessonRecord->id)
            ->assertOk()
            ->assertJsonPath('data.id', $ownLessonRecord->id);

        $this->getJson('/api/v1/lesson-records/'.$otherLessonRecord->id)
            ->assertForbidden();

        $this->patchJson('/api/v1/lesson-records/'.$ownLessonRecord->id, [
            'lesson_notes' => 'Teacher attempted update.',
        ])->assertForbidden();
    }

    public function test_student_can_only_see_their_own_lesson_records(): void
    {
        $otherStudent = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $otherStudent->assignRole('student');

        $ownLessonRecord = $this->createLessonRecord();
        $otherLessonRecord = $this->createLessonRecord(['student_id' => $otherStudent->id]);

        Sanctum::actingAs($this->student);

        $this->getJson('/api/v1/lesson-records')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownLessonRecord->id);

        $this->getJson('/api/v1/lesson-records/'.$ownLessonRecord->id)
            ->assertOk()
            ->assertJsonPath('data.id', $ownLessonRecord->id);

        $this->getJson('/api/v1/lesson-records/'.$otherLessonRecord->id)
            ->assertForbidden();

        $this->postJson('/api/v1/lesson-records', $this->validPayload())
            ->assertForbidden();

        $this->patchJson('/api/v1/lesson-records/'.$ownLessonRecord->id, [
            'lesson_notes' => 'Student attempted update.',
        ])->assertForbidden();

        $this->deleteJson('/api/v1/lesson-records/'.$ownLessonRecord->id)
            ->assertForbidden();

        $this->postJson('/api/v1/lesson-records/'.$ownLessonRecord->id.'/cancel')
            ->assertForbidden();
    }

    public function test_staff_with_lesson_record_permission_can_manage_records(): void
    {
        $staff = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('staff');
        $staff->givePermissionTo([
            'lesson_records.view',
            'lesson_records.create',
            'lesson_records.update',
            'lesson_records.delete',
        ]);

        Sanctum::actingAs($staff);

        $lessonRecord = $this->createLessonRecord();

        $this->getJson('/api/v1/lesson-records')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->postJson('/api/v1/lesson-records', $this->validPayload([
            'scheduled_date' => '2026-06-02',
        ]))->assertCreated();

        $this->patchJson('/api/v1/lesson-records/'.$lessonRecord->id, [
            'lesson_notes' => 'Staff updated record.',
        ])->assertOk();

        $this->postJson('/api/v1/lesson-records/'.$lessonRecord->id.'/cancel')
            ->assertOk()
            ->assertJsonPath('data.lesson_status', LessonRecord::STATUS_CANCELLED);

        $this->deleteJson('/api/v1/lesson-records/'.$lessonRecord->id)
            ->assertNoContent();
    }

    public function test_staff_without_lesson_record_permissions_is_blocked(): void
    {
        $staff = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('staff');
        $lessonRecord = $this->createLessonRecord();

        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/lesson-records')
            ->assertForbidden();

        $this->getJson('/api/v1/lesson-records/'.$lessonRecord->id)
            ->assertForbidden();

        $this->postJson('/api/v1/lesson-records', $this->validPayload())
            ->assertForbidden();

        $this->patchJson('/api/v1/lesson-records/'.$lessonRecord->id, [
            'lesson_notes' => 'Staff attempted update.',
        ])->assertForbidden();

        $this->deleteJson('/api/v1/lesson-records/'.$lessonRecord->id)
            ->assertForbidden();

        $this->postJson('/api/v1/lesson-records/'.$lessonRecord->id.'/cancel')
            ->assertForbidden();
    }

    public function test_lesson_record_delete_removes_record(): void
    {
        Sanctum::actingAs($this->admin);
        $lessonRecord = $this->createLessonRecord();

        $this->deleteJson('/api/v1/lesson-records/'.$lessonRecord->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('lesson_records', [
            'id' => $lessonRecord->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return [
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'scheduled_date' => '2026-06-01',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'meeting_link' => 'https://meet.example.com/lesson-1',
            'lesson_type' => LessonRecord::TYPE_BUSINESS_ENGLISH,
            'lesson_status' => LessonRecord::STATUS_SCHEDULED,
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createLessonRecord(array $overrides = []): LessonRecord
    {
        return LessonRecord::create($this->validPayload([
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
            ...$overrides,
        ]));
    }
}
