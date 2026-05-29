<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\AnnouncementReadState;
use App\Models\AnnouncementTarget;
use App\Models\CourseProgram;
use App\Models\CourseProgramStudentAssignment;
use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Notifications\SystemNotificationEmail;
use App\Services\Announcements\AnnouncementRecipientResolver;
use App\Services\Announcements\ScheduledAnnouncementPublisher;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminAnnouncementApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-15 12:00:00');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->admin->assignRole('admin');

        $this->student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->student->assignRole('student');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_can_create_list_view_and_update_announcement(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/admin/announcements', [
            'title' => 'Holiday schedule',
            'content' => 'Classes are paused next Friday.',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.title', 'Holiday schedule')
            ->assertJsonPath('data.content', 'Classes are paused next Friday.')
            ->assertJsonPath('data.body', 'Classes are paused next Friday.')
            ->assertJsonPath('data.status', Announcement::STATUS_DRAFT)
            ->assertJsonPath('data.created_by', $this->admin->id);

        $announcement = Announcement::firstOrFail();

        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->id,
            'title' => 'Holiday schedule',
            'body' => 'Classes are paused next Friday.',
            'status' => Announcement::STATUS_DRAFT,
            'author_id' => $this->admin->id,
        ]);

        $this->getJson('/api/v1/admin/announcements?per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $announcement->public_id);

        $this->getJson("/api/v1/admin/announcements/{$announcement->public_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $announcement->public_id);

        $this->patchJson("/api/v1/admin/announcements/{$announcement->public_id}", [
            'title' => 'Updated holiday schedule',
            'body' => 'Classes resume on Monday.',
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated holiday schedule')
            ->assertJsonPath('data.content', 'Classes resume on Monday.');
    }

    public function test_admin_can_schedule_and_publish_announcement(): void
    {
        Sanctum::actingAs($this->admin);

        $announcement = $this->createAnnouncement();

        $this->postJson("/api/v1/admin/announcements/{$announcement->public_id}/schedule", [
            'scheduled_at' => '2026-06-16 09:00:00',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', Announcement::STATUS_SCHEDULED)
            ->assertJsonPath('data.scheduled_at', '2026-06-16T09:00:00.000000Z')
            ->assertJsonPath('data.published_at', null);

        $this->postJson("/api/v1/admin/announcements/{$announcement->public_id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', Announcement::STATUS_PUBLISHED)
            ->assertJsonPath('data.scheduled_at', null)
            ->assertJsonPath('data.published_at', '2026-06-15T12:00:00.000000Z');
    }

    public function test_admin_can_unpublish_announcement(): void
    {
        Sanctum::actingAs($this->admin);

        $announcement = $this->createAnnouncement([
            'status' => Announcement::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);
        $announcement->targets()->create([
            'target_type' => AnnouncementTarget::TARGET_ALL,
        ]);
        app(AnnouncementRecipientResolver::class)->syncRecipients($announcement);

        $this->postJson("/api/v1/admin/announcements/{$announcement->public_id}/unpublish")
            ->assertOk()
            ->assertJsonPath('data.status', Announcement::STATUS_DRAFT)
            ->assertJsonPath('data.published_at', null)
            ->assertJsonPath('data.scheduled_at', null);

        Sanctum::actingAs($this->student);

        $this->getJson("/api/v1/announcements/{$announcement->public_id}")
            ->assertNotFound();
    }

    public function test_due_scheduled_announcements_are_published_with_notifications_idempotently(): void
    {
        NotificationFacade::fake();

        $announcement = $this->createAnnouncement([
            'status' => Announcement::STATUS_SCHEDULED,
            'scheduled_at' => '2026-06-15 11:55:00',
        ]);
        $announcement->targets()->create([
            'target_type' => AnnouncementTarget::TARGET_ROLE,
            'role' => 'student',
        ]);

        $future = $this->createAnnouncement([
            'title' => 'Future announcement',
            'status' => Announcement::STATUS_SCHEDULED,
            'scheduled_at' => '2026-06-15 12:30:00',
        ]);
        $future->targets()->create([
            'target_type' => AnnouncementTarget::TARGET_ROLE,
            'role' => 'student',
        ]);

        $publisher = app(ScheduledAnnouncementPublisher::class);
        $now = CarbonImmutable::parse('2026-06-15 12:00:00', 'UTC');

        $this->assertSame([
            'published' => 1,
            'failed' => 0,
            'notifications' => 1,
            'recipients' => 1,
        ], $publisher->publishDue($now));

        $this->assertSame([
            'published' => 0,
            'failed' => 0,
            'notifications' => 0,
            'recipients' => 0,
        ], $publisher->publishDue($now));

        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->id,
            'status' => Announcement::STATUS_PUBLISHED,
            'published_at' => '2026-06-15 12:00:00',
            'scheduled_at' => null,
        ]);
        $this->assertDatabaseHas('announcements', [
            'id' => $future->id,
            'status' => Announcement::STATUS_SCHEDULED,
        ]);

        $notification = Notification::query()
            ->where('type', Notification::TYPE_ADMIN_ANNOUNCEMENT)
            ->where('metadata->announcement_id', $announcement->id)
            ->firstOrFail();

        $this->assertSame('School announcement', $notification->title);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseCount('notification_recipients', 2);
        $this->assertDatabaseHas('notification_recipients', [
            'notification_id' => $notification->id,
            'user_id' => $this->student->id,
            'channel' => NotificationRecipient::CHANNEL_IN_PORTAL,
            'delivery_status' => NotificationRecipient::STATUS_DELIVERED,
            'sent_at' => '2026-06-15 12:00:00',
            'delivered_at' => '2026-06-15 12:00:00',
        ]);
        $this->assertDatabaseHas('notification_recipients', [
            'notification_id' => $notification->id,
            'user_id' => $this->student->id,
            'channel' => NotificationRecipient::CHANNEL_EMAIL,
            'delivery_status' => NotificationRecipient::STATUS_SENT,
        ]);
        NotificationFacade::assertSentTo($this->student, SystemNotificationEmail::class);
    }

    public function test_scheduled_announcement_failures_do_not_stop_other_due_publications(): void
    {
        NotificationFacade::fake();

        $failed = $this->createAnnouncement([
            'title' => 'Failed announcement',
            'status' => Announcement::STATUS_SCHEDULED,
            'scheduled_at' => '2026-06-15 11:55:00',
        ]);

        $published = $this->createAnnouncement([
            'title' => 'Published announcement',
            'status' => Announcement::STATUS_SCHEDULED,
            'scheduled_at' => '2026-06-15 11:56:00',
        ]);
        $published->targets()->create([
            'target_type' => AnnouncementTarget::TARGET_ROLE,
            'role' => 'student',
        ]);

        $resolver = new class($failed->id) extends AnnouncementRecipientResolver
        {
            public function __construct(private readonly int $failingAnnouncementId) {}

            public function syncRecipients(Announcement $announcement): int
            {
                if ($announcement->id === $this->failingAnnouncementId) {
                    throw new \RuntimeException('Recipient resolution failed.');
                }

                return parent::syncRecipients($announcement);
            }
        };

        $results = (new ScheduledAnnouncementPublisher($resolver))
            ->publishDue(CarbonImmutable::parse('2026-06-15 12:00:00', 'UTC'));

        $this->assertSame(1, $results['published']);
        $this->assertSame(1, $results['failed']);
        $this->assertDatabaseHas('announcements', [
            'id' => $failed->id,
            'status' => Announcement::STATUS_SCHEDULED,
            'published_at' => null,
        ]);
        $this->assertDatabaseHas('announcements', [
            'id' => $published->id,
            'status' => Announcement::STATUS_PUBLISHED,
            'published_at' => '2026-06-15 12:00:00',
        ]);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseCount('notification_recipients', 2);
    }

    public function test_scheduled_announcement_requires_future_scheduled_at(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/admin/announcements', [
            'title' => 'Invalid schedule',
            'content' => 'This should not pass.',
            'status' => Announcement::STATUS_SCHEDULED,
            'scheduled_at' => '2026-06-15 11:00:00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('scheduled_at');

        $announcement = $this->createAnnouncement();

        $this->postJson("/api/v1/admin/announcements/{$announcement->public_id}/schedule", [
            'scheduled_at' => '2026-06-15 11:00:00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('scheduled_at');
    }

    public function test_admin_can_archive_announcement_and_history_keeps_it_available(): void
    {
        Sanctum::actingAs($this->admin);

        $announcement = $this->createAnnouncement([
            'status' => Announcement::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        $this->postJson("/api/v1/admin/announcements/{$announcement->public_id}/archive")
            ->assertOk()
            ->assertJsonPath('data.status', Announcement::STATUS_ARCHIVED)
            ->assertJsonPath('data.archived_by', $this->admin->id)
            ->assertJsonPath('data.archived_at', '2026-06-15T12:00:00.000000Z');

        $this->getJson('/api/v1/admin/announcements?per_page=10')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/v1/admin/announcements?include_archived=true&per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $announcement->public_id)
            ->assertJsonPath('data.0.status', Announcement::STATUS_ARCHIVED);

        Sanctum::actingAs($this->student);

        $this->getJson('/api/v1/announcements?per_page=10')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson("/api/v1/announcements/{$announcement->public_id}")
            ->assertNotFound();
    }

    public function test_admin_can_delete_announcement_as_archive_alias(): void
    {
        Sanctum::actingAs($this->admin);

        $announcement = $this->createAnnouncement([
            'status' => Announcement::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        $this->deleteJson("/api/v1/admin/announcements/{$announcement->public_id}")
            ->assertOk()
            ->assertJsonPath('data.status', Announcement::STATUS_ARCHIVED)
            ->assertJsonPath('data.archived_by', $this->admin->id);

        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->id,
            'status' => Announcement::STATUS_ARCHIVED,
            'is_archived' => true,
        ]);
    }

    public function test_users_can_view_only_active_published_announcements(): void
    {
        $published = $this->createAnnouncement([
            'title' => 'Published announcement',
            'status' => Announcement::STATUS_PUBLISHED,
            'published_at' => '2026-06-15 11:00:00',
        ]);
        $this->createAnnouncement([
            'title' => 'Scheduled announcement',
            'status' => Announcement::STATUS_SCHEDULED,
            'scheduled_at' => '2026-06-16 09:00:00',
        ]);
        $this->createAnnouncement([
            'title' => 'Archived announcement',
            'status' => Announcement::STATUS_ARCHIVED,
            'published_at' => '2026-06-15 10:00:00',
            'is_archived' => true,
            'archived_at' => now(),
            'archived_by' => $this->admin->id,
        ]);
        $published->targets()->create([
            'target_type' => AnnouncementTarget::TARGET_ALL,
        ]);
        app(AnnouncementRecipientResolver::class)->syncRecipients($published);

        Sanctum::actingAs($this->student);

        $this->getJson('/api/v1/announcements?per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $published->public_id)
            ->assertJsonPath('data.0.status', Announcement::STATUS_PUBLISHED);
    }

    public function test_users_can_track_announcement_read_and_unread_state(): void
    {
        $published = $this->createAnnouncement([
            'title' => 'Published announcement',
            'status' => Announcement::STATUS_PUBLISHED,
            'published_at' => '2026-06-15 11:00:00',
        ]);
        $published->targets()->create([
            'target_type' => AnnouncementTarget::TARGET_ALL,
        ]);
        app(AnnouncementRecipientResolver::class)->syncRecipients($published);

        Sanctum::actingAs($this->student);

        $this->getJson('/api/v1/announcements/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1);

        $this->getJson('/api/v1/announcements?unread=true&per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.read_status', 'unread');

        $this->postJson("/api/v1/announcements/{$published->public_id}/read")
            ->assertOk()
            ->assertJsonPath('data.read_status', 'read')
            ->assertJsonPath('data.read_at', '2026-06-15T12:00:00.000000Z');

        $this->getJson('/api/v1/announcements/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);

        $this->getJson('/api/v1/announcements?status=read&per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $published->public_id);

        $this->postJson("/api/v1/announcements/{$published->public_id}/unread")
            ->assertOk()
            ->assertJsonPath('data.read_status', 'unread')
            ->assertJsonPath('data.read_at', null);

        $this->assertDatabaseHas('announcement_read_states', [
            'announcement_id' => $published->id,
            'user_id' => $this->student->id,
            'read_at' => null,
        ]);
    }

    public function test_users_can_mark_all_visible_announcements_as_read(): void
    {
        $first = $this->createAnnouncement([
            'title' => 'First announcement',
            'status' => Announcement::STATUS_PUBLISHED,
            'published_at' => '2026-06-15 10:00:00',
        ]);
        $first->targets()->create([
            'target_type' => AnnouncementTarget::TARGET_ALL,
        ]);

        $second = $this->createAnnouncement([
            'title' => 'Second announcement',
            'status' => Announcement::STATUS_PUBLISHED,
            'published_at' => '2026-06-15 11:00:00',
        ]);
        $second->targets()->create([
            'target_type' => AnnouncementTarget::TARGET_ALL,
        ]);

        app(AnnouncementRecipientResolver::class)->syncRecipients($first);
        app(AnnouncementRecipientResolver::class)->syncRecipients($second);

        AnnouncementReadState::create([
            'announcement_id' => $first->id,
            'user_id' => $this->student->id,
            'read_at' => now()->subHour(),
        ]);

        Sanctum::actingAs($this->student);

        $this->postJson('/api/v1/announcements/mark-all-read')
            ->assertOk()
            ->assertJsonPath('data.marked_read_count', 1)
            ->assertJsonPath('data.read_at', '2026-06-15T12:00:00.000000Z');

        $this->getJson('/api/v1/announcements/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);
    }

    public function test_targeted_announcements_are_visible_only_to_resolved_recipients_without_duplicates(): void
    {
        Sanctum::actingAs($this->admin);

        $otherStudent = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $otherStudent->assignRole('student');

        $response = $this->postJson('/api/v1/admin/announcements', [
            'title' => 'Student targeted notice',
            'content' => 'Visible to matching students only.',
            'status' => Announcement::STATUS_PUBLISHED,
            'targets' => [
                ['type' => AnnouncementTarget::TARGET_ROLE, 'role' => 'student'],
                ['type' => AnnouncementTarget::TARGET_USER, 'user_id' => $this->student->id],
            ],
        ]);

        $announcement = Announcement::firstWhere('title', 'Student targeted notice');

        $response
            ->assertCreated()
            ->assertJsonPath('data.recipient_count', 2);

        $this->assertDatabaseCount('announcement_recipients', 2);
        $this->assertDatabaseHas('announcement_recipients', [
            'announcement_id' => $announcement->id,
            'user_id' => $this->student->id,
        ]);

        Sanctum::actingAs($this->student);
        $this->getJson('/api/v1/announcements?per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $announcement->public_id);

        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $teacher->assignRole('teacher');

        Sanctum::actingAs($teacher);
        $this->getJson("/api/v1/announcements/{$announcement->public_id}")
            ->assertNotFound();

        Sanctum::actingAs($otherStudent);
        $this->getJson("/api/v1/announcements/{$announcement->public_id}")
            ->assertOk();
    }

    public function test_role_targeted_announcement_is_visible_only_to_users_in_that_role(): void
    {
        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $teacher->assignRole('teacher');

        Sanctum::actingAs($this->admin);

        $announcement = $this->postJson('/api/v1/admin/announcements', [
            'title' => 'Teacher notice',
            'content' => 'Visible to teachers only.',
            'status' => Announcement::STATUS_PUBLISHED,
            'targets' => [
                ['type' => AnnouncementTarget::TARGET_ROLE, 'role' => 'teacher'],
            ],
        ])->assertCreated()->json('data');

        $this->assertSame(1, $announcement['recipient_count']);

        Sanctum::actingAs($teacher);
        $this->getJson('/api/v1/announcements?per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $announcement['id']);

        Sanctum::actingAs($this->student);
        $this->getJson('/api/v1/announcements?per_page=10')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_course_and_group_targets_resolve_students_and_teachers(): void
    {
        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $teacher->assignRole('teacher');
        TeacherProfile::create([
            'user_id' => $teacher->id,
            'specialization' => 'ielts',
        ]);

        StudentProfile::create([
            'user_id' => $this->student->id,
            'assigned_teacher_id' => $teacher->id,
            'class_type' => 'morning-cohort',
        ]);

        $course = CourseProgram::factory()->create();
        CourseProgramStudentAssignment::create([
            'course_program_id' => $course->id,
            'student_id' => $this->student->id,
            'assigned_at' => now(),
            'status' => CourseProgramStudentAssignment::STATUS_ACTIVE,
        ]);
        $unassignedStudent = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $unassignedStudent->assignRole('student');
        $unassignedTeacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $unassignedTeacher->assignRole('teacher');

        Sanctum::actingAs($this->admin);

        $courseAnnouncement = $this->postJson('/api/v1/admin/announcements', [
            'title' => 'Course notice',
            'content' => 'Course-specific update.',
            'status' => Announcement::STATUS_PUBLISHED,
            'targets' => [
                ['type' => AnnouncementTarget::TARGET_COURSE, 'target_id' => $course->id],
            ],
        ])->assertCreated()->json('data');

        $groupAnnouncement = $this->postJson('/api/v1/admin/announcements', [
            'title' => 'Group notice',
            'content' => 'Group-specific update.',
            'status' => Announcement::STATUS_PUBLISHED,
            'targets' => [
                ['type' => AnnouncementTarget::TARGET_STUDENT_GROUP, 'group' => 'morning-cohort'],
                ['type' => AnnouncementTarget::TARGET_TEACHER_GROUP, 'group' => 'ielts'],
            ],
        ])->assertCreated()->json('data');

        $this->assertSame(2, $courseAnnouncement['recipient_count']);
        $this->assertSame(2, $groupAnnouncement['recipient_count']);

        Sanctum::actingAs($this->student);
        $this->getJson('/api/v1/announcements?per_page=10')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        Sanctum::actingAs($teacher);
        $this->getJson('/api/v1/announcements?per_page=10')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        Sanctum::actingAs($unassignedStudent);
        $this->getJson("/api/v1/announcements/{$courseAnnouncement['id']}")
            ->assertNotFound();

        Sanctum::actingAs($unassignedTeacher);
        $this->getJson("/api/v1/announcements/{$courseAnnouncement['id']}")
            ->assertNotFound();
    }

    public function test_specific_user_announcement_is_visible_only_to_selected_users(): void
    {
        $selectedStudent = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $selectedStudent->assignRole('student');

        Sanctum::actingAs($this->admin);

        $announcement = $this->postJson('/api/v1/admin/announcements', [
            'title' => 'Specific student notice',
            'content' => 'Visible to one selected user only.',
            'status' => Announcement::STATUS_PUBLISHED,
            'targets' => [
                ['type' => AnnouncementTarget::TARGET_USER, 'user_id' => $selectedStudent->id],
            ],
        ])->assertCreated()->json('data');

        $this->assertSame(1, $announcement['recipient_count']);

        Sanctum::actingAs($selectedStudent);
        $this->getJson("/api/v1/announcements/{$announcement['id']}")
            ->assertOk()
            ->assertJsonPath('data.id', $announcement['id']);

        Sanctum::actingAs($this->student);
        $this->getJson("/api/v1/announcements/{$announcement['id']}")
            ->assertNotFound();
    }

    public function test_staff_must_have_operational_notice_permission_to_view_resolved_announcements(): void
    {
        $staff = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('staff');

        Sanctum::actingAs($this->admin);
        $announcement = $this->postJson('/api/v1/admin/announcements', [
            'title' => 'Staff notice',
            'content' => 'Staff-only update.',
            'status' => Announcement::STATUS_PUBLISHED,
            'targets' => [
                ['type' => AnnouncementTarget::TARGET_ROLE, 'role' => 'staff'],
            ],
        ])->assertCreated()->json('data');

        $this->assertSame(0, $announcement['recipient_count']);

        $staff->givePermissionTo('dashboard.operational_notices.view');

        $this->getJson("/api/v1/admin/announcements/{$announcement['id']}/recipient-count")
            ->assertOk()
            ->assertJsonPath('data.recipient_count', 1);

        Sanctum::actingAs($staff);
        $this->getJson('/api/v1/announcements?per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $staff->revokePermissionTo('dashboard.operational_notices.view');

        $this->getJson('/api/v1/announcements?per_page=10')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_non_admin_cannot_manage_announcements(): void
    {
        Sanctum::actingAs($this->student);

        $this->postJson('/api/v1/admin/announcements', [
            'title' => 'Student attempt',
            'content' => 'Students cannot create announcements.',
        ])
            ->assertForbidden();
    }

    public function test_staff_announcement_management_depends_on_permission(): void
    {
        $staff = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('staff');

        Sanctum::actingAs($staff);

        $this->postJson('/api/v1/admin/announcements', [
            'title' => 'Staff attempt',
            'content' => 'Staff without permission cannot create announcements.',
        ])
            ->assertForbidden();

        $staff->givePermissionTo('announcements.manage');

        $response = $this->postJson('/api/v1/admin/announcements', [
            'title' => 'Staff announcement',
            'content' => 'Staff with permission can manage announcements.',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.title', 'Staff announcement')
            ->assertJsonPath('data.created_by', $staff->id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createAnnouncement(array $attributes = []): Announcement
    {
        return Announcement::create([
            'title' => 'School announcement',
            'body' => 'Announcement body.',
            'status' => Announcement::STATUS_DRAFT,
            'type' => Announcement::TYPE_ADMIN_ANNOUNCEMENT,
            'author_id' => $this->admin->id,
            ...$attributes,
        ]);
    }
}
