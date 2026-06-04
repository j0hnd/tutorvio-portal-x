<?php

namespace Tests\Feature;

use App\Enums\AuditActionType;
use App\Enums\AuditModule;
use App\Jobs\Scheduling\SendClassReminderNotification;
use App\Models\AuditLog;
use App\Models\Notification as PortalNotification;
use App\Models\NotificationRecipient;
use App\Models\Scheduling\ClassSchedule;
use App\Models\Scheduling\Holiday;
use App\Models\Scheduling\ScheduleReminder;
use App\Models\Scheduling\TeacherAvailability;
use App\Models\Scheduling\TeacherUnavailableDate;
use App\Models\User;
use App\Notifications\Scheduling\ClassScheduleReminderNotification;
use App\Services\Scheduling\ScheduleReminderService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SchedulingApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        config(['lessons.booking_locks.store' => 'array']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create(['status' => User::STATUS_ACTIVE, 'timezone' => 'Asia/Manila']);
        $this->admin->assignRole('admin');

        $this->teacher = User::factory()->create(['status' => User::STATUS_ACTIVE, 'timezone' => 'Asia/Manila']);
        $this->teacher->assignRole('teacher');

        $this->student = User::factory()->create(['status' => User::STATUS_ACTIVE, 'timezone' => 'Asia/Manila']);
        $this->student->assignRole('student');
    }

    public function test_admin_can_create_timezone_aware_class_schedule(): void
    {
        Sanctum::actingAs($this->admin);

        TeacherAvailability::create([
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '18:00',
            'timezone' => 'Asia/Manila',
        ]);

        $response = $this->postJson('/api/v1/scheduling/class-schedules', [
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'title' => 'General English',
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01 10:00:00',
            'ends_at' => '2026-06-01 11:00:00',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', ClassSchedule::STATUS_SCHEDULED)
            ->assertJsonPath('data.timezone', 'Asia/Manila');

        $this->assertDatabaseHas('class_schedules', [
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'starts_at' => '2026-06-01 02:00:00',
            'ends_at' => '2026-06-01 03:00:00',
        ]);

        $auditLog = AuditLog::query()
            ->where('action_type', AuditActionType::LESSON_CREATED->value)
            ->where('module', AuditModule::LESSONS->value)
            ->where('target_entity_type', 'class_schedule')
            ->latest('id')
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertSame($this->admin->id, $auditLog->actor_user_id);
        $this->assertSame($this->student->id, $auditLog->metadata['student_id'] ?? null);
        $this->assertSame($this->teacher->id, $auditLog->metadata['teacher_id'] ?? null);
    }

    public function test_schedule_creation_rejects_unavailable_teacher_time(): void
    {
        Sanctum::actingAs($this->admin);

        TeacherAvailability::create([
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '18:00',
            'timezone' => 'Asia/Manila',
        ]);

        TeacherUnavailableDate::create([
            'teacher_id' => $this->teacher->id,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01 01:30:00',
            'ends_at' => '2026-06-01 02:30:00',
            'reason' => 'Training',
        ]);

        $this->postJson('/api/v1/scheduling/class-schedules', [
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01 10:00:00',
            'ends_at' => '2026-06-01 11:00:00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('starts_at');
    }

    public function test_student_can_only_see_their_own_schedules(): void
    {
        $otherStudent = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $otherStudent->assignRole('student');

        ClassSchedule::create([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'status' => ClassSchedule::STATUS_SCHEDULED,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01 02:00:00',
            'ends_at' => '2026-06-01 03:00:00',
        ]);

        ClassSchedule::create([
            'student_id' => $otherStudent->id,
            'teacher_id' => $this->teacher->id,
            'status' => ClassSchedule::STATUS_SCHEDULED,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-02 02:00:00',
            'ends_at' => '2026-06-02 03:00:00',
        ]);

        Sanctum::actingAs($this->student);

        $response = $this->getJson('/api/v1/scheduling/class-schedules');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.student_id', $this->student->public_id);

        $this->assertNoNumericIdsInPayload($response->json('data.0'));
    }

    public function test_holidays_block_schedule_creation(): void
    {
        Sanctum::actingAs($this->admin);

        TeacherAvailability::create([
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '18:00',
            'timezone' => 'Asia/Manila',
        ]);

        Holiday::create([
            'name' => 'Foundation Day',
            'date' => '2026-06-01',
            'timezone' => 'Asia/Manila',
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/scheduling/class-schedules', [
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01 10:00:00',
            'ends_at' => '2026-06-01 11:00:00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('starts_at');
    }

    public function test_active_holiday_blocks_cannot_overlap_for_same_timezone(): void
    {
        Holiday::create([
            'name' => 'Foundation Day',
            'date' => '2026-06-01',
            'timezone' => 'Asia/Manila',
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/scheduling/holidays', [
            'name' => 'Duplicate Foundation Day',
            'date' => '2026-06-01',
            'timezone' => 'Asia/Manila',
            'is_active' => true,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date');

        $this->postJson('/api/v1/scheduling/holidays', [
            'name' => 'Annual Foundation Day',
            'date' => '2024-06-01',
            'timezone' => 'Asia/Manila',
            'repeats_annually' => true,
            'is_active' => true,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date');
    }

    public function test_student_can_book_one_time_lesson_with_assigned_teacher(): void
    {
        $this->student->studentProfile()->create([
            'assigned_teacher_id' => $this->teacher->id,
        ]);

        TeacherAvailability::create([
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '18:00',
            'timezone' => 'Asia/Manila',
        ]);

        Sanctum::actingAs($this->student);

        $response = $this->postJson('/api/v1/scheduling/lesson-bookings', [
            'teacher_id' => $this->teacher->id,
            'title' => 'Conversation practice',
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01T10:00:00+08:00',
            'ends_at' => '2026-06-01T11:00:00+08:00',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.student_id', $this->student->public_id)
            ->assertJsonPath('data.teacher_id', $this->teacher->public_id)
            ->assertJsonPath('data.status', ClassSchedule::STATUS_PENDING_CONFIRMATION);

        $this->assertNoNumericIdsInPayload($response->json('data'));

        $this->assertDatabaseHas('class_schedules', [
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'status' => ClassSchedule::STATUS_PENDING_CONFIRMATION,
            'starts_at' => '2026-06-01 02:00:00',
            'ends_at' => '2026-06-01 03:00:00',
        ]);
    }

    public function test_trial_booking_uses_thirty_minute_class_and_one_hour_teacher_block(): void
    {
        $this->student->studentProfile()->create([
            'assigned_teacher_id' => $this->teacher->id,
            'class_type' => 'trial',
        ]);

        TeacherAvailability::create([
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '18:00',
            'timezone' => 'Asia/Manila',
        ]);

        Sanctum::actingAs($this->student);

        $this->postJson('/api/v1/scheduling/lesson-bookings', [
            'teacher_id' => $this->teacher->id,
            'title' => 'Trial class',
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01T10:00:00+08:00',
            'ends_at' => '2026-06-01T10:30:00+08:00',
        ])
            ->assertCreated()
            ->assertJsonPath('data.class_type', ClassSchedule::CLASS_TYPE_TRIAL)
            ->assertJsonPath('data.ends_at', '2026-06-01T02:30:00.000000Z')
            ->assertJsonPath('data.teacher_blocked_until', '2026-06-01T03:00:00.000000Z');

        $this->assertDatabaseHas('class_schedules', [
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'class_type' => ClassSchedule::CLASS_TYPE_TRIAL,
            'starts_at' => '2026-06-01 02:00:00',
            'ends_at' => '2026-06-01 02:30:00',
            'teacher_blocked_until' => '2026-06-01 03:00:00',
        ]);

        $this->postJson('/api/v1/scheduling/lesson-bookings', [
            'teacher_id' => $this->teacher->id,
            'class_type' => ClassSchedule::CLASS_TYPE_REGULAR,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01T10:30:00+08:00',
            'ends_at' => '2026-06-01T11:30:00+08:00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('starts_at');

        $this->getJson('/api/v1/scheduling/calendar?view=day&date=2026-06-01&timezone=Asia/Manila')
            ->assertOk()
            ->assertJsonPath('data.booked_lessons.0.ends_at', '2026-06-01T10:30:00+08:00')
            ->assertJsonPath('data.booked_lessons.0.teacher_blocked_until', '2026-06-01T11:00:00+08:00');
    }

    public function test_trial_booking_requires_availability_for_full_teacher_block(): void
    {
        $this->student->studentProfile()->create([
            'assigned_teacher_id' => $this->teacher->id,
            'class_type' => 'trial',
        ]);

        TeacherAvailability::create([
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '10:00',
            'end_time' => '10:30',
            'timezone' => 'Asia/Manila',
        ]);

        Sanctum::actingAs($this->student);

        $this->postJson('/api/v1/scheduling/lesson-bookings', [
            'teacher_id' => $this->teacher->id,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01T10:00:00+08:00',
            'ends_at' => '2026-06-01T10:30:00+08:00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('starts_at');
    }

    public function test_student_booking_requires_assigned_teacher(): void
    {
        $otherTeacher = User::factory()->create(['status' => User::STATUS_ACTIVE, 'timezone' => 'Asia/Manila']);
        $otherTeacher->assignRole('teacher');

        $this->student->studentProfile()->create([
            'assigned_teacher_id' => $this->teacher->id,
        ]);

        Sanctum::actingAs($this->student);

        $this->postJson('/api/v1/scheduling/lesson-bookings', [
            'teacher_id' => $otherTeacher->id,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01T10:00:00+08:00',
            'ends_at' => '2026-06-01T11:00:00+08:00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('teacher_id');
    }

    public function test_student_booking_rejects_conflicting_slot(): void
    {
        $this->student->studentProfile()->create([
            'assigned_teacher_id' => $this->teacher->id,
        ]);

        TeacherAvailability::create([
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '18:00',
            'timezone' => 'Asia/Manila',
        ]);

        ClassSchedule::create([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'status' => ClassSchedule::STATUS_SCHEDULED,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01 02:30:00',
            'ends_at' => '2026-06-01 03:30:00',
        ]);

        Sanctum::actingAs($this->student);

        $this->postJson('/api/v1/scheduling/lesson-bookings', [
            'teacher_id' => $this->teacher->id,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01T10:00:00+08:00',
            'ends_at' => '2026-06-01T11:00:00+08:00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('starts_at');
    }

    public function test_one_time_booking_prevents_exact_double_booking(): void
    {
        $this->student->studentProfile()->create([
            'assigned_teacher_id' => $this->teacher->id,
        ]);

        TeacherAvailability::create([
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '18:00',
            'timezone' => 'Asia/Manila',
        ]);

        Sanctum::actingAs($this->student);

        $payload = [
            'teacher_id' => $this->teacher->id,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01T10:00:00+08:00',
            'ends_at' => '2026-06-01T11:00:00+08:00',
        ];

        $this->postJson('/api/v1/scheduling/lesson-bookings', $payload)
            ->assertCreated();

        $this->postJson('/api/v1/scheduling/lesson-bookings', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('starts_at');

        $this->assertDatabaseCount('class_schedules', 1);
    }

    public function test_student_booking_returns_clear_error_when_teacher_slot_is_locked(): void
    {
        $this->student->studentProfile()->create([
            'assigned_teacher_id' => $this->teacher->id,
        ]);

        $availability = TeacherAvailability::create([
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '18:00',
            'timezone' => 'Asia/Manila',
        ]);

        $lock = Cache::store('array')->lock(
            "tvio:lesson_booking:teacher_availability:{$this->teacher->id}:{$availability->id}:2026-06-01",
            60
        );

        $this->assertTrue($lock->get());

        try {
            Sanctum::actingAs($this->student);

            $this->postJson('/api/v1/scheduling/lesson-bookings', [
                'teacher_id' => $this->teacher->id,
                'timezone' => 'Asia/Manila',
                'starts_at' => '2026-06-01T10:00:00+08:00',
                'ends_at' => '2026-06-01T11:00:00+08:00',
            ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('starts_at')
                ->assertJsonPath('errors.starts_at.0', 'This lesson slot is already being booked. Please try another time or retry shortly.');

            $this->assertDatabaseCount('class_schedules', 0);
        } finally {
            $lock->release();
        }
    }

    public function test_student_booking_releases_locks_after_success(): void
    {
        $this->student->studentProfile()->create([
            'assigned_teacher_id' => $this->teacher->id,
        ]);

        $availability = TeacherAvailability::create([
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '18:00',
            'timezone' => 'Asia/Manila',
        ]);

        Sanctum::actingAs($this->student);

        $this->postJson('/api/v1/scheduling/lesson-bookings', [
            'teacher_id' => $this->teacher->id,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01T10:00:00+08:00',
            'ends_at' => '2026-06-01T11:00:00+08:00',
        ])->assertCreated();

        $teacherLock = Cache::store('array')->lock(
            "tvio:lesson_booking:teacher_availability:{$this->teacher->id}:{$availability->id}:2026-06-01",
            60
        );
        $studentLock = Cache::store('array')->lock(
            "tvio:lesson_booking:student:{$this->student->id}:1780279200:1780282800",
            60
        );

        $this->assertTrue($teacherLock->get());
        $this->assertTrue($studentLock->get());

        $teacherLock->release();
        $studentLock->release();
    }

    public function test_student_booking_respects_unavailable_dates_and_holidays(): void
    {
        $this->student->studentProfile()->create([
            'assigned_teacher_id' => $this->teacher->id,
        ]);

        TeacherAvailability::create([
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '18:00',
            'timezone' => 'Asia/Manila',
        ]);

        TeacherUnavailableDate::create([
            'teacher_id' => $this->teacher->id,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01 01:30:00',
            'ends_at' => '2026-06-01 02:30:00',
            'reason' => 'Training',
        ]);

        Holiday::create([
            'name' => 'Foundation Day',
            'date' => '2026-06-08',
            'timezone' => 'Asia/Manila',
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->student);

        $this->postJson('/api/v1/scheduling/lesson-bookings', [
            'teacher_id' => $this->teacher->id,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01T10:00:00+08:00',
            'ends_at' => '2026-06-01T11:00:00+08:00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('starts_at');

        $this->postJson('/api/v1/scheduling/lesson-bookings', [
            'teacher_id' => $this->teacher->id,
            'status' => ClassSchedule::STATUS_SCHEDULED,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-08T10:00:00+08:00',
            'ends_at' => '2026-06-08T11:00:00+08:00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('starts_at');
    }

    public function test_admin_can_create_recurring_class_schedules(): void
    {
        Sanctum::actingAs($this->admin);

        TeacherAvailability::create([
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '18:00',
            'timezone' => 'Asia/Manila',
        ]);

        $response = $this->postJson('/api/v1/scheduling/class-schedules/recurring', [
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'title' => 'Weekly English',
            'timezone' => 'Asia/Manila',
            'start_date' => '2026-06-01',
            'occurrence_count' => 3,
            'day_of_week' => 1,
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.created_count', 3)
            ->assertJsonPath('data.skipped_count', 0)
            ->assertJsonPath('data.requested_occurrences', 3)
            ->assertJsonCount(3, 'data.created')
            ->assertJsonCount(0, 'data.skipped');

        $this->assertDatabaseHas('class_schedules', [
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'title' => 'Weekly English',
            'starts_at' => '2026-06-01 02:00:00',
            'ends_at' => '2026-06-01 03:00:00',
        ]);

        $this->assertDatabaseHas('class_schedules', [
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'starts_at' => '2026-06-15 02:00:00',
            'ends_at' => '2026-06-15 03:00:00',
        ]);
    }

    public function test_recurring_class_schedule_returns_skipped_conflicting_and_blocked_dates(): void
    {
        Sanctum::actingAs($this->admin);

        TeacherAvailability::create([
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '18:00',
            'timezone' => 'Asia/Manila',
        ]);

        ClassSchedule::create([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'status' => ClassSchedule::STATUS_SCHEDULED,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-08 02:30:00',
            'ends_at' => '2026-06-08 03:30:00',
        ]);

        TeacherUnavailableDate::create([
            'teacher_id' => $this->teacher->id,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-15 01:30:00',
            'ends_at' => '2026-06-15 02:30:00',
            'reason' => 'Training',
        ]);

        Holiday::create([
            'name' => 'Foundation Day',
            'date' => '2026-06-22',
            'timezone' => 'Asia/Manila',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/scheduling/class-schedules/recurring', [
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'timezone' => 'Asia/Manila',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-22',
            'day_of_week' => 1,
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.created_count', 1)
            ->assertJsonPath('data.skipped_count', 3)
            ->assertJsonPath('data.requested_occurrences', 4)
            ->assertJsonPath('data.skipped.0.date', '2026-06-08')
            ->assertJsonPath('data.skipped.1.date', '2026-06-15')
            ->assertJsonPath('data.skipped.2.date', '2026-06-22');

        $this->assertDatabaseHas('class_schedules', [
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'starts_at' => '2026-06-01 02:00:00',
            'ends_at' => '2026-06-01 03:00:00',
        ]);

        $this->assertDatabaseMissing('class_schedules', [
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'starts_at' => '2026-06-15 02:00:00',
            'ends_at' => '2026-06-15 03:00:00',
        ]);
    }

    public function test_recurring_class_schedule_blocks_student_conflicts_and_annual_holidays(): void
    {
        $otherTeacher = User::factory()->create(['status' => User::STATUS_ACTIVE, 'timezone' => 'Asia/Manila']);
        $otherTeacher->assignRole('teacher');

        Sanctum::actingAs($this->admin);

        TeacherAvailability::create([
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '18:00',
            'timezone' => 'Asia/Manila',
        ]);

        ClassSchedule::create([
            'student_id' => $this->student->id,
            'teacher_id' => $otherTeacher->id,
            'status' => ClassSchedule::STATUS_PENDING_CONFIRMATION,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01 02:30:00',
            'ends_at' => '2026-06-01 03:30:00',
        ]);

        Holiday::create([
            'name' => 'Annual Foundation Day',
            'date' => '2024-06-08',
            'timezone' => 'Asia/Manila',
            'repeats_annually' => true,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/scheduling/class-schedules/recurring', [
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'timezone' => 'Asia/Manila',
            'start_date' => '2026-06-01',
            'occurrence_count' => 3,
            'day_of_week' => 1,
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.created_count', 1)
            ->assertJsonPath('data.skipped_count', 2)
            ->assertJsonPath('data.skipped.0.date', '2026-06-01')
            ->assertJsonPath('data.skipped.0.reason', 'The student already has a class scheduled during this time.')
            ->assertJsonPath('data.skipped.1.date', '2026-06-08')
            ->assertJsonPath('data.skipped.1.reason', 'The class falls on a configured holiday.');

        $this->assertDatabaseHas('class_schedules', [
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'starts_at' => '2026-06-15 02:00:00',
            'ends_at' => '2026-06-15 03:00:00',
        ]);
    }

    public function test_teacher_can_manage_only_their_own_availability(): void
    {
        $otherTeacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $otherTeacher->assignRole('teacher');

        Sanctum::actingAs($this->teacher);

        $response = $this->postJson('/api/v1/scheduling/teacher-availabilities', [
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'timezone' => 'Asia/Manila',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.teacher_id', $this->teacher->public_id);
        $availability = TeacherAvailability::where('public_id', $response->json('data.id'))->firstOrFail();

        $this->postJson('/api/v1/scheduling/teacher-availabilities', [
            'teacher_id' => $otherTeacher->id,
            'day_of_week' => 1,
            'start_time' => '13:00',
            'end_time' => '15:00',
            'timezone' => 'Asia/Manila',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('teacher_id');

        $this->patchJson('/api/v1/scheduling/teacher-availabilities/'.$availability->public_id, [
            'end_time' => '13:00',
        ])->assertOk();

        $this->deleteJson('/api/v1/scheduling/teacher-availabilities/'.$availability->public_id)
            ->assertNoContent();
    }

    public function test_admin_can_view_all_teacher_availability(): void
    {
        $otherTeacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $otherTeacher->assignRole('teacher');

        TeacherAvailability::create([
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'timezone' => 'Asia/Manila',
        ]);

        TeacherAvailability::create([
            'teacher_id' => $otherTeacher->id,
            'day_of_week' => 2,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'timezone' => 'Asia/Manila',
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/scheduling/teacher-availabilities')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_student_can_only_view_assigned_teacher_availability(): void
    {
        $otherTeacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $otherTeacher->assignRole('teacher');

        $this->student->studentProfile()->create([
            'assigned_teacher_id' => $this->teacher->id,
        ]);

        TeacherAvailability::create([
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'timezone' => 'Asia/Manila',
        ]);

        TeacherAvailability::create([
            'teacher_id' => $otherTeacher->id,
            'day_of_week' => 1,
            'start_time' => '13:00',
            'end_time' => '16:00',
            'timezone' => 'Asia/Manila',
        ]);

        Sanctum::actingAs($this->student);

        $this->getJson('/api/v1/scheduling/teacher-availabilities')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.teacher_id', $this->teacher->public_id);
    }

    public function test_overlapping_teacher_availability_is_rejected(): void
    {
        TeacherAvailability::create([
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'timezone' => 'Asia/Manila',
        ]);

        Sanctum::actingAs($this->teacher);

        $this->postJson('/api/v1/scheduling/teacher-availabilities', [
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '11:00',
            'end_time' => '13:00',
            'timezone' => 'Asia/Manila',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('start_time');
    }

    public function test_teacher_cannot_update_or_delete_booked_availability_slot(): void
    {
        $availability = TeacherAvailability::create([
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'timezone' => 'Asia/Manila',
        ]);

        ClassSchedule::create([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'status' => ClassSchedule::STATUS_SCHEDULED,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01 02:00:00',
            'ends_at' => '2026-06-01 03:00:00',
        ]);

        Sanctum::actingAs($this->teacher);

        $this->patchJson('/api/v1/scheduling/teacher-availabilities/'.$availability->public_id, [
            'end_time' => '13:00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('availability');

        $this->deleteJson('/api/v1/scheduling/teacher-availabilities/'.$availability->public_id)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('availability');
    }

    public function test_teacher_can_create_timezone_aware_unavailable_date(): void
    {
        Sanctum::actingAs($this->teacher);

        $this->postJson('/api/v1/scheduling/teacher-unavailable-dates', [
            'teacher_id' => $this->teacher->id,
            'starts_at' => '2026-06-01 10:00:00',
            'ends_at' => '2026-06-01 12:00:00',
            'timezone' => 'Asia/Manila',
            'reason' => 'Training',
        ])
            ->assertCreated()
            ->assertJsonPath('data.teacher_id', $this->teacher->public_id);

        $this->assertDatabaseHas('teacher_unavailable_dates', [
            'teacher_id' => $this->teacher->id,
            'starts_at' => '2026-06-01 02:00:00',
            'ends_at' => '2026-06-01 04:00:00',
            'timezone' => 'Asia/Manila',
        ]);
    }

    public function test_teacher_unavailable_dates_cannot_overlap(): void
    {
        TeacherUnavailableDate::create([
            'teacher_id' => $this->teacher->id,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01 01:00:00',
            'ends_at' => '2026-06-01 03:00:00',
            'reason' => 'Training',
        ]);

        Sanctum::actingAs($this->teacher);

        $this->postJson('/api/v1/scheduling/teacher-unavailable-dates', [
            'teacher_id' => $this->teacher->id,
            'starts_at' => '2026-06-01 10:00:00',
            'ends_at' => '2026-06-01 12:00:00',
            'timezone' => 'Asia/Manila',
            'reason' => 'Workshop',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('starts_at');
    }

    public function test_all_day_unavailable_dates_are_normalized_in_their_timezone(): void
    {
        Sanctum::actingAs($this->teacher);

        $this->postJson('/api/v1/scheduling/teacher-unavailable-dates', [
            'teacher_id' => $this->teacher->id,
            'starts_at' => '2026-06-01',
            'ends_at' => '2026-06-01',
            'timezone' => 'Asia/Manila',
            'is_all_day' => true,
            'reason' => 'Leave',
        ])
            ->assertCreated()
            ->assertJsonPath('data.is_all_day', true);

        $this->assertDatabaseHas('teacher_unavailable_dates', [
            'teacher_id' => $this->teacher->id,
            'starts_at' => '2026-05-31 16:00:00',
            'ends_at' => '2026-06-01 15:59:59',
            'timezone' => 'Asia/Manila',
            'is_all_day' => true,
        ]);
    }

    public function test_admin_calendar_view_returns_all_scheduling_blocks_for_requested_month(): void
    {
        $otherTeacher = User::factory()->create(['status' => User::STATUS_ACTIVE, 'timezone' => 'Asia/Manila']);
        $otherTeacher->assignRole('teacher');

        TeacherAvailability::create([
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'timezone' => 'Asia/Manila',
        ]);

        ClassSchedule::create([
            'student_id' => $this->student->id,
            'teacher_id' => $otherTeacher->id,
            'status' => ClassSchedule::STATUS_PENDING_CONFIRMATION,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-02 01:00:00',
            'ends_at' => '2026-06-02 02:00:00',
        ]);

        TeacherUnavailableDate::create([
            'teacher_id' => $otherTeacher->id,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-03 01:00:00',
            'ends_at' => '2026-06-03 02:00:00',
            'reason' => 'Training',
        ]);

        Holiday::create([
            'name' => 'Foundation Day',
            'date' => '2026-06-04',
            'timezone' => 'Asia/Manila',
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/scheduling/calendar?view=month&date=2026-06-01&timezone=Asia/Manila')
            ->assertOk()
            ->assertJsonPath('data.view', 'month')
            ->assertJsonPath('data.timezone', 'Asia/Manila')
            ->assertJsonPath('data.booked_lessons.0.status', ClassSchedule::STATUS_PENDING_CONFIRMATION)
            ->assertJsonCount(5, 'data.availability')
            ->assertJsonCount(1, 'data.booked_lessons')
            ->assertJsonCount(1, 'data.unavailable_dates')
            ->assertJsonCount(1, 'data.holiday_blocks');
    }

    public function test_calendar_includes_annual_holiday_blocks_across_year_boundaries(): void
    {
        Holiday::create([
            'name' => 'New Year',
            'date' => '2024-01-01',
            'timezone' => 'Asia/Manila',
            'repeats_annually' => true,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/scheduling/calendar?view=week&date=2026-12-28&timezone=Asia/Manila')
            ->assertOk()
            ->assertJsonCount(1, 'data.holiday_blocks')
            ->assertJsonPath('data.holiday_blocks.0.date', '2027-01-01');
    }

    public function test_teacher_calendar_is_scoped_to_own_schedule_and_availability(): void
    {
        $otherTeacher = User::factory()->create(['status' => User::STATUS_ACTIVE, 'timezone' => 'Asia/Manila']);
        $otherTeacher->assignRole('teacher');

        TeacherAvailability::create([
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'timezone' => 'Asia/Manila',
        ]);

        TeacherAvailability::create([
            'teacher_id' => $otherTeacher->id,
            'day_of_week' => 1,
            'start_time' => '13:00',
            'end_time' => '16:00',
            'timezone' => 'Asia/Manila',
        ]);

        ClassSchedule::create([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'status' => ClassSchedule::STATUS_SCHEDULED,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01 01:00:00',
            'ends_at' => '2026-06-01 02:00:00',
        ]);

        ClassSchedule::create([
            'student_id' => $this->student->id,
            'teacher_id' => $otherTeacher->id,
            'status' => ClassSchedule::STATUS_SCHEDULED,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01 05:00:00',
            'ends_at' => '2026-06-01 06:00:00',
        ]);

        Sanctum::actingAs($this->teacher);

        $this->getJson('/api/v1/scheduling/calendar?view=day&date=2026-06-01&timezone=Asia/Manila')
            ->assertOk()
            ->assertJsonCount(1, 'data.availability')
            ->assertJsonCount(1, 'data.booked_lessons')
            ->assertJsonPath('data.availability.0.teacher.id', $this->teacher->public_id)
            ->assertJsonPath('data.booked_lessons.0.teacher.id', $this->teacher->public_id)
            ->assertJsonPath('data.booked_lessons.0.starts_at', '2026-06-01T09:00:00+08:00');
    }

    public function test_student_calendar_only_includes_own_classes_and_assigned_teacher_availability(): void
    {
        $otherStudent = User::factory()->create(['status' => User::STATUS_ACTIVE, 'timezone' => 'Asia/Manila']);
        $otherStudent->assignRole('student');

        $otherTeacher = User::factory()->create(['status' => User::STATUS_ACTIVE, 'timezone' => 'Asia/Manila']);
        $otherTeacher->assignRole('teacher');

        $this->student->studentProfile()->create([
            'assigned_teacher_id' => $this->teacher->id,
        ]);

        TeacherAvailability::create([
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'timezone' => 'Asia/Manila',
        ]);

        TeacherAvailability::create([
            'teacher_id' => $otherTeacher->id,
            'day_of_week' => 1,
            'start_time' => '13:00',
            'end_time' => '16:00',
            'timezone' => 'Asia/Manila',
        ]);

        ClassSchedule::create([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'status' => ClassSchedule::STATUS_COMPLETED,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01 01:00:00',
            'ends_at' => '2026-06-01 02:00:00',
        ]);

        ClassSchedule::create([
            'student_id' => $otherStudent->id,
            'teacher_id' => $this->teacher->id,
            'status' => ClassSchedule::STATUS_SCHEDULED,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01 03:00:00',
            'ends_at' => '2026-06-01 04:00:00',
        ]);

        Sanctum::actingAs($this->student);

        $this->getJson('/api/v1/scheduling/calendar?view=week&date=2026-06-01&timezone=Asia/Manila')
            ->assertOk()
            ->assertJsonCount(1, 'data.availability')
            ->assertJsonCount(1, 'data.booked_lessons')
            ->assertJsonPath('data.availability.0.teacher.id', $this->teacher->public_id)
            ->assertJsonPath('data.booked_lessons.0.student.id', $this->student->public_id)
            ->assertJsonPath('data.booked_lessons.0.status', ClassSchedule::STATUS_COMPLETED);
    }

    public function test_day_week_and_month_calendar_views_scope_requested_range_and_role_data(): void
    {
        $otherTeacher = User::factory()->create(['status' => User::STATUS_ACTIVE, 'timezone' => 'Asia/Manila']);
        $otherTeacher->assignRole('teacher');

        $this->student->studentProfile()->create([
            'assigned_teacher_id' => $this->teacher->id,
        ]);

        TeacherAvailability::create([
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'timezone' => 'Asia/Manila',
        ]);

        TeacherAvailability::create([
            'teacher_id' => $otherTeacher->id,
            'day_of_week' => 1,
            'start_time' => '13:00',
            'end_time' => '16:00',
            'timezone' => 'Asia/Manila',
        ]);

        ClassSchedule::create([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'status' => ClassSchedule::STATUS_SCHEDULED,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01 01:00:00',
            'ends_at' => '2026-06-01 02:00:00',
        ]);

        ClassSchedule::create([
            'student_id' => $this->student->id,
            'teacher_id' => $otherTeacher->id,
            'status' => ClassSchedule::STATUS_SCHEDULED,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-08 05:00:00',
            'ends_at' => '2026-06-08 06:00:00',
        ]);

        Sanctum::actingAs($this->student);

        $this->getJson('/api/v1/scheduling/calendar?view=day&date=2026-06-01&timezone=Asia/Manila')
            ->assertOk()
            ->assertJsonPath('data.view', 'day')
            ->assertJsonPath('data.range.starts_at', '2026-06-01T00:00:00+08:00')
            ->assertJsonCount(1, 'data.availability')
            ->assertJsonCount(1, 'data.booked_lessons')
            ->assertJsonPath('data.booked_lessons.0.teacher.id', $this->teacher->public_id);

        $this->getJson('/api/v1/scheduling/calendar?view=week&date=2026-06-01&timezone=Asia/Manila')
            ->assertOk()
            ->assertJsonPath('data.view', 'week')
            ->assertJsonPath('data.range.starts_at', '2026-06-01T00:00:00+08:00')
            ->assertJsonPath('data.range.ends_at', '2026-06-07T23:59:59+08:00')
            ->assertJsonCount(1, 'data.availability')
            ->assertJsonCount(1, 'data.booked_lessons');

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/scheduling/calendar?view=month&date=2026-06-01&timezone=Asia/Manila')
            ->assertOk()
            ->assertJsonPath('data.view', 'month')
            ->assertJsonPath('data.range.starts_at', '2026-06-01T00:00:00+08:00')
            ->assertJsonPath('data.range.ends_at', '2026-06-30T23:59:59+08:00')
            ->assertJsonCount(10, 'data.availability')
            ->assertJsonCount(2, 'data.booked_lessons');
    }

    public function test_class_status_update_is_stored_and_returned(): void
    {
        $schedule = ClassSchedule::create([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'status' => ClassSchedule::STATUS_SCHEDULED,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01 02:00:00',
            'ends_at' => '2026-06-01 03:00:00',
        ]);

        Sanctum::actingAs($this->teacher);

        $this->patchJson('/api/v1/scheduling/class-schedules/'.$schedule->public_id.'/status', [
            'status' => ClassSchedule::STATUS_COMPLETED,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', ClassSchedule::STATUS_COMPLETED);

        $this->assertDatabaseHas('class_schedules', [
            'id' => $schedule->id,
            'status' => ClassSchedule::STATUS_COMPLETED,
            'updated_by' => $this->teacher->id,
        ]);

        $this->getJson('/api/v1/scheduling/class-schedules/'.$schedule->public_id)
            ->assertOk()
            ->assertJsonPath('data.status', ClassSchedule::STATUS_COMPLETED);

        $auditLog = AuditLog::query()
            ->where('action_type', AuditActionType::SCHEDULE_UPDATED->value)
            ->where('module', AuditModule::SCHEDULING->value)
            ->where('target_entity_type', 'class_schedule')
            ->where('target_entity_id', $schedule->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertSame($this->teacher->id, $auditLog->actor_user_id);
        $this->assertSame(ClassSchedule::STATUS_SCHEDULED, $auditLog->metadata['previous_status'] ?? null);
        $this->assertSame(ClassSchedule::STATUS_COMPLETED, $auditLog->metadata['new_status'] ?? null);
    }

    public function test_teacher_schedule_datetime_update_creates_audit_log_with_before_after_values(): void
    {
        TeacherAvailability::create([
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '18:00',
            'timezone' => 'Asia/Manila',
        ]);

        $schedule = ClassSchedule::create([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'status' => ClassSchedule::STATUS_SCHEDULED,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01 02:00:00',
            'ends_at' => '2026-06-01 03:00:00',
        ]);

        Sanctum::actingAs($this->teacher);

        $this->patchJson('/api/v1/scheduling/class-schedules/'.$schedule->public_id, [
            'starts_at' => '2026-06-01 12:00:00',
            'ends_at' => '2026-06-01 13:00:00',
            'timezone' => 'Asia/Manila',
        ])->assertOk();

        $auditLog = AuditLog::query()
            ->where('action_type', AuditActionType::SCHEDULE_UPDATED->value)
            ->where('module', AuditModule::SCHEDULING->value)
            ->where('target_entity_type', 'class_schedule')
            ->where('target_entity_id', $schedule->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertSame($this->teacher->id, $auditLog->actor_user_id);
        $this->assertTrue($auditLog->metadata['teacher_or_admin_change'] ?? false);
        $this->assertArrayHasKey('starts_at', $auditLog->metadata['changed_fields'] ?? []);
        $this->assertArrayHasKey('ends_at', $auditLog->metadata['changed_fields'] ?? []);
        $this->assertSame('2026-06-01T02:00:00+00:00', $auditLog->metadata['schedule_before']['starts_at'] ?? null);
        $this->assertSame('2026-06-01T04:00:00+00:00', $auditLog->metadata['schedule_after']['starts_at'] ?? null);
    }

    public function test_reminder_service_queues_student_and_teacher_reminders_without_duplicates(): void
    {
        $service = app(ScheduleReminderService::class);

        ClassSchedule::create([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'title' => 'Conversation practice',
            'status' => ClassSchedule::STATUS_SCHEDULED,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01 02:00:00',
            'ends_at' => '2026-06-01 03:00:00',
        ]);

        $now = CarbonImmutable::parse('2026-05-31 01:30:00', 'UTC');

        $this->assertSame(4, $service->queueUpcoming(48, $now));
        $this->assertSame(0, $service->queueUpcoming(48, $now));

        $this->assertDatabaseCount('schedule_reminders', 4);
        $this->assertDatabaseHas('schedule_reminders', [
            'user_id' => $this->student->id,
            'channel' => 'email',
            'status' => ScheduleReminder::STATUS_PENDING,
            'scheduled_for' => '2026-05-31 02:00:00',
        ]);
        $this->assertDatabaseHas('schedule_reminders', [
            'user_id' => $this->teacher->id,
            'channel' => 'email',
            'status' => ScheduleReminder::STATUS_PENDING,
            'scheduled_for' => '2026-06-01 01:00:00',
        ]);
    }

    public function test_reminder_service_only_queues_valid_upcoming_scheduled_classes(): void
    {
        $service = app(ScheduleReminderService::class);
        $now = CarbonImmutable::parse('2026-05-31 01:30:00', 'UTC');

        foreach ([
            ClassSchedule::STATUS_CANCELLED,
            ClassSchedule::STATUS_COMPLETED,
            ClassSchedule::STATUS_MISSED_BY_STUDENT,
            ClassSchedule::STATUS_MISSED_BY_TEACHER,
            ClassSchedule::STATUS_RESCHEDULED,
            ClassSchedule::STATUS_PENDING_CONFIRMATION,
        ] as $index => $status) {
            ClassSchedule::create([
                'student_id' => $this->student->id,
                'teacher_id' => $this->teacher->id,
                'status' => $status,
                'timezone' => 'Asia/Manila',
                'starts_at' => CarbonImmutable::parse('2026-06-01 02:00:00', 'UTC')->addHours($index),
                'ends_at' => CarbonImmutable::parse('2026-06-01 03:00:00', 'UTC')->addHours($index),
            ]);
        }

        ClassSchedule::create([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'status' => ClassSchedule::STATUS_SCHEDULED,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01 08:00:00',
            'ends_at' => '2026-06-01 09:00:00',
        ]);

        $this->assertSame(4, $service->queueUpcoming(48, $now));
        $this->assertDatabaseCount('schedule_reminders', 4);
    }

    public function test_reminder_service_dispatches_due_reminder_jobs(): void
    {
        Queue::fake();

        $service = app(ScheduleReminderService::class);
        $schedule = ClassSchedule::create([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'title' => 'Grammar review',
            'status' => ClassSchedule::STATUS_SCHEDULED,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01 02:00:00',
            'ends_at' => '2026-06-01 03:00:00',
        ]);

        $reminder = ScheduleReminder::create([
            'class_schedule_id' => $schedule->id,
            'user_id' => $this->student->id,
            'channel' => 'email',
            'status' => ScheduleReminder::STATUS_PENDING,
            'scheduled_for' => '2026-06-01 01:00:00',
        ]);

        $now = CarbonImmutable::parse('2026-06-01 01:00:00', 'UTC');

        $this->assertSame(1, $service->sendDue($now));

        Queue::assertPushed(SendClassReminderNotification::class, function (SendClassReminderNotification $job) use ($reminder, $now): bool {
            return $job->scheduleReminderId === $reminder->id
                && $job->dueAt === $now->toIso8601String();
        });

        $this->assertDatabaseHas('schedule_reminders', [
            'id' => $reminder->id,
            'status' => ScheduleReminder::STATUS_PENDING,
        ]);
    }

    public function test_reminder_job_sends_due_email_and_tracks_status(): void
    {
        Notification::fake();

        $service = app(ScheduleReminderService::class);
        $schedule = ClassSchedule::create([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'title' => 'Grammar review',
            'status' => ClassSchedule::STATUS_SCHEDULED,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01 02:00:00',
            'ends_at' => '2026-06-01 03:00:00',
        ]);

        $reminder = ScheduleReminder::create([
            'class_schedule_id' => $schedule->id,
            'user_id' => $this->student->id,
            'channel' => 'email',
            'status' => ScheduleReminder::STATUS_PENDING,
            'scheduled_for' => '2026-06-01 01:00:00',
        ]);

        $now = CarbonImmutable::parse('2026-06-01 01:00:00', 'UTC');

        $this->assertTrue($service->sendReminder($reminder->id, $now));

        Notification::assertSentTo($this->student, ClassScheduleReminderNotification::class);
        $portalNotification = PortalNotification::query()
            ->where('type', PortalNotification::TYPE_CLASS_REMINDER)
            ->where('metadata->schedule_reminder_id', $reminder->id)
            ->firstOrFail();

        $this->assertDatabaseHas('notification_recipients', [
            'notification_id' => $portalNotification->id,
            'user_id' => $this->student->id,
            'channel' => NotificationRecipient::CHANNEL_IN_PORTAL,
            'delivery_status' => NotificationRecipient::STATUS_DELIVERED,
        ]);
        $this->assertDatabaseHas('notification_recipients', [
            'notification_id' => $portalNotification->id,
            'user_id' => $this->student->id,
            'channel' => NotificationRecipient::CHANNEL_EMAIL,
            'delivery_status' => NotificationRecipient::STATUS_SENT,
        ]);
        $this->assertDatabaseHas('schedule_reminders', [
            'class_schedule_id' => $schedule->id,
            'user_id' => $this->student->id,
            'status' => ScheduleReminder::STATUS_SENT,
            'sent_at' => '2026-06-01 01:00:00',
        ]);
    }

    public function test_due_reminders_for_ineligible_classes_are_cancelled_not_sent(): void
    {
        Notification::fake();

        $service = app(ScheduleReminderService::class);
        $schedule = ClassSchedule::create([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'status' => ClassSchedule::STATUS_CANCELLED,
            'timezone' => 'Asia/Manila',
            'starts_at' => '2026-06-01 02:00:00',
            'ends_at' => '2026-06-01 03:00:00',
        ]);

        ScheduleReminder::create([
            'class_schedule_id' => $schedule->id,
            'user_id' => $this->teacher->id,
            'channel' => 'email',
            'status' => ScheduleReminder::STATUS_PENDING,
            'scheduled_for' => '2026-06-01 01:00:00',
        ]);

        $this->assertSame(1, $service->sendDue(CarbonImmutable::parse('2026-06-01 01:00:00', 'UTC')));

        Notification::assertNothingSent();
        $this->assertDatabaseHas('schedule_reminders', [
            'class_schedule_id' => $schedule->id,
            'user_id' => $this->teacher->id,
            'status' => ScheduleReminder::STATUS_CANCELLED,
        ]);
    }

    private function assertNoNumericIdsInPayload(mixed $payload): void
    {
        if (! is_array($payload)) {
            return;
        }

        foreach ($payload as $key => $value) {
            if ((is_string($key) && ($key === 'id' || str_ends_with($key, '_id'))) && $value !== null) {
                $this->assertIsString($value, "Expected {$key} to be an opaque public ID.");
                $this->assertFalse(is_numeric($value), "Expected {$key} not to expose a numeric database ID.");
            }

            $this->assertNoNumericIdsInPayload($value);
        }
    }
}
