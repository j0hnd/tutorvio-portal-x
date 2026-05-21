<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class LessonJoinApiTest extends TestCase
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

        $this->admin = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->admin->assignRole('admin');

        $this->teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->teacher->assignRole('teacher');

        $this->student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->student->assignRole('student');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_assigned_student_can_retrieve_join_link_when_available(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01 08:50:00'));
        $lesson = $this->createJoinableLesson();

        Sanctum::actingAs($this->student);

        $this->getJson('/api/v1/lessons/'.$lesson->id.'/join')
            ->assertOk()
            ->assertJsonPath('data.lesson_id', $lesson->id)
            ->assertJsonPath('data.meeting_provider', Lesson::PROVIDER_GOOGLE_MEET)
            ->assertJsonPath('data.can_join', true)
            ->assertJsonPath('data.is_join_available', true)
            ->assertJsonPath('data.starts_at', '2026-06-01T09:00:00Z')
            ->assertJsonPath('data.ends_at', '2026-06-01T10:00:00Z')
            ->assertJsonPath('data.available_until', '2026-06-01T10:15:00Z')
            ->assertJsonPath('data.meeting_link', 'https://meet.example.com/secure-lesson');
    }

    public function test_assigned_teacher_can_retrieve_join_link_when_available(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01 08:50:00'));
        $lesson = $this->createJoinableLesson();

        Sanctum::actingAs($this->teacher);

        $this->getJson('/api/v1/lessons/'.$lesson->id.'/join')
            ->assertOk()
            ->assertJsonPath('data.is_join_available', true)
            ->assertJsonPath('data.meeting_link', 'https://meet.example.com/secure-lesson');
    }

    public function test_admin_can_retrieve_join_link_when_available(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01 08:50:00'));
        $lesson = $this->createJoinableLesson();

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/lessons/'.$lesson->id.'/join')
            ->assertOk()
            ->assertJsonPath('data.is_join_available', true)
            ->assertJsonPath('data.meeting_link', 'https://meet.example.com/secure-lesson');
    }

    public function test_assigned_user_receives_safe_metadata_before_join_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01 08:30:00'));
        $lesson = $this->createJoinableLesson();

        Sanctum::actingAs($this->student);

        $response = $this->getJson('/api/v1/lessons/'.$lesson->id.'/join')
            ->assertOk()
            ->assertJsonPath('data.can_join', false)
            ->assertJsonPath('data.is_join_available', false)
            ->assertJsonPath('data.available_from', '2026-06-01T08:45:00Z')
            ->assertJsonPath('data.available_until', '2026-06-01T10:15:00Z')
            ->assertJsonPath('data.starts_at', '2026-06-01T09:00:00Z')
            ->assertJsonPath('data.ends_at', '2026-06-01T10:00:00Z')
            ->assertJsonPath('data.seconds_until_available', 900)
            ->assertJsonPath('data.reason', 'not_yet_available')
            ->assertJsonPath('data.join_starts_at', '2026-06-01T08:45:00.000000Z')
            ->assertJsonPath('data.join_ends_at', '2026-06-01T10:15:00.000000Z')
            ->assertJsonPath('data.meeting_link', null);

        $this->assertStringNotContainsString('https://meet.example.com/secure-lesson', json_encode($response->json('data')));
        $this->assertStringNotContainsString('meeting_metadata', json_encode($response->json('data')));
    }

    public function test_unassigned_student_teacher_and_staff_cannot_access_unrelated_lesson_link(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01 08:50:00'));
        $lesson = $this->createJoinableLesson();

        $otherStudent = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $otherStudent->assignRole('student');

        $otherTeacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $otherTeacher->assignRole('teacher');

        $staff = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $staff->assignRole('staff');

        foreach ([$otherStudent, $otherTeacher, $staff] as $user) {
            Sanctum::actingAs($user);

            $this->getJson('/api/v1/lessons/'.$lesson->id.'/join')
                ->assertForbidden()
                ->assertJsonMissing(['meeting_link' => 'https://meet.example.com/secure-lesson']);
        }
    }

    public function test_join_endpoint_requires_authentication(): void
    {
        $lesson = $this->createJoinableLesson();

        $this->getJson('/api/v1/lessons/'.$lesson->id.'/join')
            ->assertUnauthorized();
    }

    public function test_default_join_window_opens_before_start_and_closes_after_end(): void
    {
        $lesson = $this->createJoinableLesson([
            'join_available_from' => null,
            'join_available_until' => null,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-06-01 08:44:59'));
        Sanctum::actingAs($this->student);

        $this->getJson('/api/v1/lessons/'.$lesson->id.'/join')
            ->assertOk()
            ->assertJsonPath('data.can_join', false)
            ->assertJsonPath('data.available_from', '2026-06-01T08:45:00Z')
            ->assertJsonPath('data.available_until', '2026-06-01T10:15:00Z')
            ->assertJsonPath('data.seconds_until_available', 1)
            ->assertJsonPath('data.reason', 'not_yet_available')
            ->assertJsonPath('data.meeting_link', null);

        Carbon::setTestNow(Carbon::parse('2026-06-01 08:45:00'));

        $this->getJson('/api/v1/lessons/'.$lesson->id.'/join')
            ->assertOk()
            ->assertJsonPath('data.can_join', true)
            ->assertJsonPath('data.seconds_until_available', 0)
            ->assertJsonPath('data.meeting_link', 'https://meet.example.com/secure-lesson');

        Carbon::setTestNow(Carbon::parse('2026-06-01 10:15:01'));

        $this->getJson('/api/v1/lessons/'.$lesson->id.'/join')
            ->assertOk()
            ->assertJsonPath('data.can_join', false)
            ->assertJsonPath('data.reason', 'expired')
            ->assertJsonPath('data.meeting_link', null);
    }

    private function createJoinableLesson(array $overrides = []): Lesson
    {
        return Lesson::create([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'start_time' => Carbon::parse('2026-06-01 09:00:00'),
            'end_time' => Carbon::parse('2026-06-01 10:00:00'),
            'status' => 'scheduled',
            'meeting_link' => 'https://meet.example.com/secure-lesson',
            'meeting_provider' => Lesson::PROVIDER_GOOGLE_MEET,
            'meeting_metadata' => [
                'google_event_id' => 'private-event-id',
            ],
            'join_available_from' => Carbon::parse('2026-06-01 08:45:00'),
            'join_available_until' => Carbon::parse('2026-06-01 10:15:00'),
            ...$overrides,
        ]);
    }
}
