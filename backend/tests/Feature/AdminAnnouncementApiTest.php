<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
            ->assertJsonPath('data.0.id', $announcement->id);

        $this->getJson("/api/v1/admin/announcements/{$announcement->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $announcement->id);

        $this->patchJson("/api/v1/admin/announcements/{$announcement->id}", [
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

        $this->postJson("/api/v1/admin/announcements/{$announcement->id}/schedule", [
            'scheduled_at' => '2026-06-16 09:00:00',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', Announcement::STATUS_SCHEDULED)
            ->assertJsonPath('data.scheduled_at', '2026-06-16T09:00:00.000000Z')
            ->assertJsonPath('data.published_at', null);

        $this->postJson("/api/v1/admin/announcements/{$announcement->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', Announcement::STATUS_PUBLISHED)
            ->assertJsonPath('data.scheduled_at', null)
            ->assertJsonPath('data.published_at', '2026-06-15T12:00:00.000000Z');
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

        $this->postJson("/api/v1/admin/announcements/{$announcement->id}/schedule", [
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

        $this->postJson("/api/v1/admin/announcements/{$announcement->id}/archive")
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
            ->assertJsonPath('data.0.id', $announcement->id)
            ->assertJsonPath('data.0.status', Announcement::STATUS_ARCHIVED);

        Sanctum::actingAs($this->student);

        $this->getJson('/api/v1/announcements?per_page=10')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson("/api/v1/announcements/{$announcement->id}")
            ->assertNotFound();
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

        Sanctum::actingAs($this->student);

        $this->getJson('/api/v1/announcements?per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $published->id)
            ->assertJsonPath('data.0.status', Announcement::STATUS_PUBLISHED);
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
