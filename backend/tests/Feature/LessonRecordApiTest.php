<?php

namespace Tests\Feature;

use App\Enums\AuditActionType;
use App\Enums\AuditModule;
use App\Models\AuditLog;
use App\Models\LessonRecord;
use App\Models\Material;
use App\Models\Subscription;
use App\Models\SubscriptionHistory;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_can_create_lesson_record(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/lesson-records', $this->validPayload([
            'lesson_notes' => 'Focused on speaking fluency.',
            'homework_instructions' => 'Complete unit 4 exercises.',
            'homework_due_date' => '2026-06-08',
            'attendance_status' => LessonRecord::ATTENDANCE_PRESENT,
            'internal_remarks' => 'Parent asked for a progress review.',
        ]));

        $response
            ->assertCreated()
            ->assertJsonPath('data.student_id', $this->student->public_id)
            ->assertJsonPath('data.teacher_id', $this->teacher->public_id)
            ->assertJsonPath('data.lesson_type', LessonRecord::TYPE_BUSINESS_ENGLISH)
            ->assertJsonPath('data.lesson_status', LessonRecord::STATUS_SCHEDULED)
            ->assertJsonPath('data.homework_details', 'Complete unit 4 exercises.')
            ->assertJsonPath('data.homework_instructions', 'Complete unit 4 exercises.')
            ->assertJsonPath('data.homework_due_date', '2026-06-08')
            ->assertJsonPath('data.attendance_status', LessonRecord::ATTENDANCE_PRESENT)
            ->assertJsonPath('data.internal_remarks', 'Parent asked for a progress review.')
            ->assertJsonPath('data.is_completed', false);

        $this->assertDatabaseHas('lesson_records', [
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'scheduled_date' => '2026-06-01 00:00:00',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'homework_details' => 'Complete unit 4 exercises.',
            'homework_due_date' => '2026-06-08 00:00:00',
            'attendance_status' => LessonRecord::ATTENDANCE_PRESENT,
            'internal_remarks' => 'Parent asked for a progress review.',
            'created_by' => $this->admin->id,
        ]);

        $lessonId = LessonRecord::where('public_id', $response->json('data.id'))->value('id');

        $lessonLog = AuditLog::query()
            ->where('action_type', AuditActionType::LESSON_CREATED->value)
            ->where('module', AuditModule::LESSONS->value)
            ->where('target_entity_type', 'lesson_record')
            ->where('target_entity_id', $lessonId)
            ->latest('id')
            ->first();

        $attendanceLog = AuditLog::query()
            ->where('action_type', AuditActionType::ATTENDANCE_MARKED->value)
            ->where('module', AuditModule::ATTENDANCE->value)
            ->where('target_entity_type', 'lesson_record')
            ->where('target_entity_id', $lessonId)
            ->latest('id')
            ->first();

        $this->assertNotNull($lessonLog);
        $this->assertNotNull($attendanceLog);
        $this->assertSame($this->admin->id, $lessonLog->actor_user_id);
        $this->assertSame($this->admin->id, $attendanceLog->actor_user_id);
        $this->assertNull($attendanceLog->metadata['previous_status'] ?? null);
        $this->assertSame(LessonRecord::ATTENDANCE_PRESENT, $attendanceLog->metadata['new_status'] ?? null);
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

    public function test_lesson_type_validation_rejects_unknown_values(): void
    {
        Sanctum::actingAs($this->admin);
        $lessonRecord = $this->createLessonRecord();

        $this->postJson('/api/v1/lesson-records', $this->validPayload([
            'lesson_type' => 'grammar_drills',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lesson_type');

        $this->patchJson('/api/v1/lesson-records/'.$lessonRecord->public_id, [
            'lesson_type' => 'grammar_drills',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lesson_type');

        $this->getJson('/api/v1/lesson-records?lesson_type=grammar_drills')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lesson_type');
    }

    public function test_lesson_status_validation_rejects_unknown_values(): void
    {
        Sanctum::actingAs($this->admin);
        $lessonRecord = $this->createLessonRecord();

        $this->postJson('/api/v1/lesson-records', $this->validPayload([
            'lesson_status' => 'waiting_for_feedback',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lesson_status');

        $this->patchJson('/api/v1/lesson-records/'.$lessonRecord->public_id, [
            'lesson_status' => 'waiting_for_feedback',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lesson_status');

        $this->getJson('/api/v1/lesson-records?lesson_status=waiting_for_feedback')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lesson_status');
    }

    public function test_meeting_provider_validation_rejects_unknown_values(): void
    {
        Sanctum::actingAs($this->admin);
        $lessonRecord = $this->createLessonRecord();

        $this->postJson('/api/v1/lesson-records', $this->validPayload([
            'meeting_provider' => 'zoom',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('meeting_provider');

        $this->patchJson('/api/v1/lesson-records/'.$lessonRecord->public_id, [
            'meeting_provider' => 'zoom',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('meeting_provider');
    }

    public function test_meeting_link_is_only_exposed_when_lesson_record_is_joinable(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01 08:30:00'));
        Sanctum::actingAs($this->student);

        $lessonRecord = $this->createLessonRecord([
            'meeting_provider' => LessonRecord::PROVIDER_GOOGLE_MEET,
            'meeting_metadata' => [
                'google_event_id' => 'calendar-event-1',
                'conference_id' => 'meet-conference-1',
            ],
            'join_available_from' => '2026-06-01 08:45:00',
            'join_available_until' => '2026-06-01 10:15:00',
        ]);

        $this->getJson('/api/v1/lesson-records/'.$lessonRecord->public_id)
            ->assertOk()
            ->assertJsonPath('data.meeting_provider', LessonRecord::PROVIDER_GOOGLE_MEET)
            ->assertJsonPath('data.is_join_available', false)
            ->assertJsonMissingPath('data.meeting_link')
            ->assertJsonMissingPath('data.meeting_metadata');

        Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00'));

        $this->getJson('/api/v1/lesson-records/'.$lessonRecord->public_id)
            ->assertOk()
            ->assertJsonPath('data.is_join_available', true)
            ->assertJsonPath('data.meeting_link', 'https://meet.example.com/lesson-1')
            ->assertJsonPath('data.meeting_metadata.google_event_id', 'calendar-event-1');

        Carbon::setTestNow();
    }

    public function test_admin_can_list_show_update_and_cancel_lesson_record(): void
    {
        Sanctum::actingAs($this->admin);
        $lessonRecord = $this->createLessonRecord();

        $this->getJson('/api/v1/lesson-records?student_id='.$this->student->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $lessonRecord->public_id);

        $this->getJson('/api/v1/lesson-records/'.$lessonRecord->public_id)
            ->assertOk()
            ->assertJsonPath('data.student.id', $this->student->public_id)
            ->assertJsonPath('data.teacher.id', $this->teacher->public_id);

        $this->patchJson('/api/v1/lesson-records/'.$lessonRecord->public_id, [
            'lesson_status' => LessonRecord::STATUS_COMPLETED,
            'is_completed' => true,
            'lesson_notes' => 'Student completed the target lesson.',
        ])
            ->assertOk()
            ->assertJsonPath('data.lesson_status', LessonRecord::STATUS_COMPLETED)
            ->assertJsonPath('data.is_completed', true)
            ->assertJsonPath('data.completed_by', $this->admin->id);

        $this->postJson('/api/v1/lesson-records/'.$lessonRecord->public_id.'/cancel', [
            'reason' => 'Student requested a later slot.',
        ])
            ->assertOk()
            ->assertJsonPath('data.lesson_status', LessonRecord::STATUS_CANCELLED)
            ->assertJsonPath('data.is_completed', false);
    }

    public function test_completion_flag_sets_status_timestamp_and_actor(): void
    {
        Sanctum::actingAs($this->admin);
        $lessonRecord = $this->createLessonRecord();

        $this->patchJson('/api/v1/lesson-records/'.$lessonRecord->public_id, [
            'is_completed' => true,
            'attendance_status' => LessonRecord::ATTENDANCE_PRESENT,
            'lesson_notes' => 'Student completed all lesson objectives.',
        ])
            ->assertOk()
            ->assertJsonPath('data.lesson_status', LessonRecord::STATUS_COMPLETED)
            ->assertJsonPath('data.is_completed', true)
            ->assertJsonPath('data.completed_by', $this->admin->id);

        $lessonRecord->refresh();

        $this->assertTrue($lessonRecord->is_completed);
        $this->assertSame(LessonRecord::STATUS_COMPLETED, $lessonRecord->lesson_status);
        $this->assertNotNull($lessonRecord->completed_at);
        $this->assertSame($this->admin->id, $lessonRecord->completed_by);

        $attendanceLog = AuditLog::query()
            ->where('action_type', AuditActionType::ATTENDANCE_MARKED->value)
            ->where('module', AuditModule::ATTENDANCE->value)
            ->where('target_entity_type', 'lesson_record')
            ->where('target_entity_id', $lessonRecord->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($attendanceLog);
        $this->assertNull($attendanceLog->metadata['previous_status'] ?? null);
        $this->assertSame(LessonRecord::ATTENDANCE_PRESENT, $attendanceLog->metadata['new_status'] ?? null);
    }

    public function test_completed_lesson_consumes_active_subscription_balance_once(): void
    {
        Sanctum::actingAs($this->admin);
        $subscription = Subscription::factory()->create([
            'user_id' => $this->student->id,
            'total_lesson_count' => 3,
            'consumed_lesson_count' => 1,
            'remaining_lesson_count' => 2,
            'status' => Subscription::STATUS_ACTIVE,
            'is_frozen' => false,
        ]);
        $lessonRecord = $this->createLessonRecord();

        $this->patchJson('/api/v1/lesson-records/'.$lessonRecord->public_id, [
            'lesson_status' => LessonRecord::STATUS_COMPLETED,
            'is_completed' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.lesson_balance_consumed_subscription_id', $subscription->public_id);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'consumed_lesson_count' => 2,
            'remaining_lesson_count' => 1,
        ]);
        $this->assertDatabaseHas('subscription_histories', [
            'subscription_id' => $subscription->id,
            'event_type' => SubscriptionHistory::EVENT_LESSONS_CONSUMED,
            'created_by' => $this->admin->id,
        ]);

        $this->patchJson('/api/v1/lesson-records/'.$lessonRecord->public_id, [
            'lesson_notes' => 'Updated after completion.',
        ])->assertOk();

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'consumed_lesson_count' => 2,
            'remaining_lesson_count' => 1,
        ]);
        $this->assertSame(1, SubscriptionHistory::where('subscription_id', $subscription->id)
            ->where('event_type', SubscriptionHistory::EVENT_LESSONS_CONSUMED)
            ->count());
    }

    public function test_completed_lessons_cannot_consume_more_than_available_balance(): void
    {
        Sanctum::actingAs($this->admin);
        $subscription = Subscription::factory()->create([
            'user_id' => $this->student->id,
            'total_lesson_count' => 1,
            'consumed_lesson_count' => 0,
            'remaining_lesson_count' => 1,
            'status' => Subscription::STATUS_ACTIVE,
            'is_frozen' => false,
        ]);
        $firstLessonRecord = $this->createLessonRecord();
        $secondLessonRecord = $this->createLessonRecord([
            'scheduled_date' => '2026-06-02',
        ]);

        $this->patchJson('/api/v1/lesson-records/'.$firstLessonRecord->public_id, [
            'lesson_status' => LessonRecord::STATUS_COMPLETED,
            'is_completed' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.lesson_balance_consumed_subscription_id', $subscription->public_id);

        $this->patchJson('/api/v1/lesson-records/'.$secondLessonRecord->public_id, [
            'lesson_status' => LessonRecord::STATUS_COMPLETED,
            'is_completed' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.lesson_balance_consumed_subscription_id', null);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'consumed_lesson_count' => 1,
            'remaining_lesson_count' => 0,
        ]);
        $this->assertSame(1, SubscriptionHistory::where('subscription_id', $subscription->id)
            ->where('event_type', SubscriptionHistory::EVENT_LESSONS_CONSUMED)
            ->count());
    }

    public function test_completed_lesson_ignores_frozen_and_inactive_subscriptions(): void
    {
        Sanctum::actingAs($this->admin);
        $frozen = Subscription::factory()->create([
            'user_id' => $this->student->id,
            'total_lesson_count' => 3,
            'consumed_lesson_count' => 1,
            'remaining_lesson_count' => 2,
            'status' => Subscription::STATUS_INACTIVE,
            'is_frozen' => true,
        ]);
        $inactive = Subscription::factory()->create([
            'user_id' => $this->student->id,
            'total_lesson_count' => 3,
            'consumed_lesson_count' => 1,
            'remaining_lesson_count' => 2,
            'status' => Subscription::STATUS_INACTIVE,
            'is_frozen' => false,
        ]);
        $lessonRecord = $this->createLessonRecord();

        $this->patchJson('/api/v1/lesson-records/'.$lessonRecord->public_id, [
            'lesson_status' => LessonRecord::STATUS_COMPLETED,
            'is_completed' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.lesson_balance_consumed_subscription_id', null);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $frozen->id,
            'consumed_lesson_count' => 1,
            'remaining_lesson_count' => 2,
        ]);
        $this->assertDatabaseHas('subscriptions', [
            'id' => $inactive->id,
            'consumed_lesson_count' => 1,
            'remaining_lesson_count' => 2,
        ]);
        $this->assertDatabaseMissing('subscription_histories', [
            'event_type' => SubscriptionHistory::EVENT_LESSONS_CONSUMED,
        ]);
    }

    public function test_lesson_notes_and_homework_are_saved_on_update(): void
    {
        Sanctum::actingAs($this->admin);
        $lessonRecord = $this->createLessonRecord([
            'lesson_notes' => 'Initial note.',
            'homework_details' => 'Initial homework.',
        ]);

        $this->patchJson('/api/v1/lesson-records/'.$lessonRecord->public_id, [
            'lesson_notes' => 'Reviewed past tense and pronunciation.',
            'homework_instructions' => 'Write five sentences using past tense.',
            'homework_due_date' => '2026-06-09',
        ])
            ->assertOk()
            ->assertJsonPath('data.lesson_notes', 'Reviewed past tense and pronunciation.')
            ->assertJsonPath('data.homework_details', 'Write five sentences using past tense.')
            ->assertJsonPath('data.homework_instructions', 'Write five sentences using past tense.')
            ->assertJsonPath('data.homework_due_date', '2026-06-09');

        $this->assertDatabaseHas('lesson_records', [
            'id' => $lessonRecord->id,
            'lesson_notes' => 'Reviewed past tense and pronunciation.',
            'homework_details' => 'Write five sentences using past tense.',
            'homework_due_date' => '2026-06-09 00:00:00',
        ]);
    }

    public function test_lesson_record_can_link_existing_materials(): void
    {
        $worksheet = Material::create([
            'title' => 'Unit 4 worksheet',
            'description' => 'Speaking fluency practice',
            'url' => 'https://cdn.example.com/unit-4.pdf',
        ]);
        $video = Material::create([
            'title' => 'Pronunciation video',
            'url' => 'https://cdn.example.com/pronunciation.mp4',
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/lesson-records', $this->validPayload([
            'material_ids' => [$worksheet->id, $video->id],
        ]));

        $response
            ->assertCreated()
            ->assertJsonCount(2, 'data.materials')
            ->assertJsonPath('data.materials.0.title', 'Unit 4 worksheet')
            ->assertJsonPath('data.materials.0.url', 'https://cdn.example.com/unit-4.pdf')
            ->assertJsonPath('data.materials.1.title', 'Pronunciation video');

        $lessonRecordPublicId = $response->json('data.id');
        $lessonRecordId = LessonRecord::where('public_id', $lessonRecordPublicId)->value('id');

        $this->assertDatabaseHas('lesson_record_materials', [
            'lesson_record_id' => $lessonRecordId,
            'material_id' => $worksheet->id,
        ]);

        Sanctum::actingAs($this->student);

        $this->getJson('/api/v1/lesson-records/'.$lessonRecordPublicId)
            ->assertOk()
            ->assertJsonCount(2, 'data.materials')
            ->assertJsonPath('data.materials.0.title', 'Unit 4 worksheet');
    }

    public function test_lesson_record_materials_can_be_replaced_or_cleared(): void
    {
        $worksheet = Material::create(['title' => 'Unit 4 worksheet']);
        $video = Material::create(['title' => 'Pronunciation video']);
        $lessonRecord = $this->createLessonRecord();
        $lessonRecord->materials()->attach($worksheet->id);

        Sanctum::actingAs($this->admin);

        $this->patchJson('/api/v1/lesson-records/'.$lessonRecord->public_id, [
            'material_ids' => [$video->id],
        ])
            ->assertOk()
            ->assertJsonCount(1, 'data.materials')
            ->assertJsonPath('data.materials.0.id', $video->id);

        $this->assertDatabaseMissing('lesson_record_materials', [
            'lesson_record_id' => $lessonRecord->id,
            'material_id' => $worksheet->id,
        ]);
        $this->assertDatabaseHas('lesson_record_materials', [
            'lesson_record_id' => $lessonRecord->id,
            'material_id' => $video->id,
        ]);

        $this->patchJson('/api/v1/lesson-records/'.$lessonRecord->public_id, [
            'material_ids' => [],
        ])
            ->assertOk()
            ->assertJsonCount(0, 'data.materials');

        $this->assertDatabaseMissing('lesson_record_materials', [
            'lesson_record_id' => $lessonRecord->id,
            'material_id' => $video->id,
        ]);
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
            ->assertJsonPath('data.0.id', $ownLessonRecord->public_id);

        $this->getJson('/api/v1/lesson-records/'.$ownLessonRecord->public_id)
            ->assertOk()
            ->assertJsonPath('data.id', $ownLessonRecord->public_id)
            ->assertJsonMissingPath('data.internal_remarks');

        $this->getJson('/api/v1/lesson-records/'.$otherLessonRecord->public_id)
            ->assertForbidden();

        $this->patchJson('/api/v1/lesson-records/'.$ownLessonRecord->public_id, [
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
            ->assertJsonPath('data.0.id', $ownLessonRecord->public_id);

        $this->getJson('/api/v1/lesson-records/'.$ownLessonRecord->public_id)
            ->assertOk()
            ->assertJsonPath('data.id', $ownLessonRecord->public_id);

        $this->getJson('/api/v1/lesson-records/'.$otherLessonRecord->public_id)
            ->assertForbidden();

        $this->postJson('/api/v1/lesson-records', $this->validPayload())
            ->assertForbidden();

        $this->patchJson('/api/v1/lesson-records/'.$ownLessonRecord->public_id, [
            'lesson_notes' => 'Student attempted update.',
        ])->assertForbidden();

        $this->deleteJson('/api/v1/lesson-records/'.$ownLessonRecord->public_id)
            ->assertForbidden();

        $this->postJson('/api/v1/lesson-records/'.$ownLessonRecord->public_id.'/cancel')
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

        $this->patchJson('/api/v1/lesson-records/'.$lessonRecord->public_id, [
            'lesson_notes' => 'Staff updated record.',
        ])->assertOk();

        $this->postJson('/api/v1/lesson-records/'.$lessonRecord->public_id.'/cancel')
            ->assertOk()
            ->assertJsonPath('data.lesson_status', LessonRecord::STATUS_CANCELLED);

        $this->deleteJson('/api/v1/lesson-records/'.$lessonRecord->public_id)
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

        $this->getJson('/api/v1/lesson-records/'.$lessonRecord->public_id)
            ->assertForbidden();

        $this->postJson('/api/v1/lesson-records', $this->validPayload())
            ->assertForbidden();

        $this->patchJson('/api/v1/lesson-records/'.$lessonRecord->public_id, [
            'lesson_notes' => 'Staff attempted update.',
        ])->assertForbidden();

        $this->deleteJson('/api/v1/lesson-records/'.$lessonRecord->public_id)
            ->assertForbidden();

        $this->postJson('/api/v1/lesson-records/'.$lessonRecord->public_id.'/cancel')
            ->assertForbidden();
    }

    public function test_lesson_record_delete_removes_record(): void
    {
        Sanctum::actingAs($this->admin);
        $lessonRecord = $this->createLessonRecord();

        $this->deleteJson('/api/v1/lesson-records/'.$lessonRecord->public_id)
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
