<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\CourseProgram;
use App\Models\Homework;
use App\Models\Invoice;
use App\Models\LearningResource;
use App\Models\Lesson;
use App\Models\MessageThread;
use App\Models\Notification;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class BackfillPublicIdsCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var list<string>
     */
    private const TABLES = [
        'users',
        'lessons',
        'invoices',
        'subscriptions',
        'homeworks',
        'message_threads',
        'learning_resources',
        'course_programs',
        'announcements',
        'notifications',
        'audit_logs',
    ];

    public function test_it_backfills_missing_public_ids_in_chunks_and_is_idempotent(): void
    {
        $student = User::factory()->create();
        $teacher = User::factory()->create();
        $preservedUser = User::factory()->create();
        $preservedPublicId = $preservedUser->public_id;

        Lesson::create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'start_time' => '2026-06-10 09:00:00',
            'end_time' => '2026-06-10 10:00:00',
            'status' => Lesson::STATUS_SCHEDULED,
        ]);
        Invoice::factory()->create();
        Subscription::factory()->create();
        Homework::factory()->create();
        MessageThread::create([
            'title' => 'Lesson follow-up',
            'thread_type' => MessageThread::TYPE_STUDENT_TEACHER,
            'status' => MessageThread::STATUS_ACTIVE,
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'created_by' => $student->id,
        ]);
        LearningResource::create([
            'title' => 'Business English worksheet',
            'resource_type' => LearningResource::TYPE_WORKSHEET,
            'created_by' => $teacher->id,
        ]);
        CourseProgram::factory()->create();
        Announcement::create([
            'title' => 'Portal maintenance',
            'body' => 'The portal will be unavailable briefly.',
            'status' => Announcement::STATUS_DRAFT,
            'type' => Announcement::TYPE_ADMIN_ANNOUNCEMENT,
            'author_id' => $teacher->id,
        ]);
        Notification::create([
            'title' => 'Homework reminder',
            'body' => 'A homework deadline is approaching.',
            'type' => Notification::TYPE_HOMEWORK_REMINDER,
            'sender_id' => $teacher->id,
        ]);
        AuditLog::factory()->create();

        foreach (self::TABLES as $table) {
            DB::table($table)->update(['public_id' => null]);
        }

        DB::table('users')
            ->where('id', $preservedUser->id)
            ->update(['public_id' => $preservedPublicId]);

        $this->artisan('tvio:backfill-public-ids', ['--chunk' => 1])
            ->assertExitCode(0);

        foreach (self::TABLES as $table) {
            $this->assertSame(0, DB::table($table)->whereNull('public_id')->count(), "{$table} still has null public IDs.");
            $this->assertTrue(
                DB::table($table)->pluck('public_id')->every(fn (string $publicId): bool => Str::isUlid($publicId)),
                "{$table} contains a non-ULID public ID."
            );
        }

        $this->assertSame($preservedPublicId, User::findOrFail($preservedUser->id)->public_id);

        $publicIds = collect(self::TABLES)
            ->mapWithKeys(fn (string $table): array => [
                $table => DB::table($table)->orderBy('id')->pluck('public_id', 'id')->all(),
            ])
            ->all();

        $this->artisan('tvio:backfill-public-ids', ['--chunk' => 1])
            ->assertExitCode(0);

        foreach ($publicIds as $table => $ids) {
            $this->assertSame($ids, DB::table($table)->orderBy('id')->pluck('public_id', 'id')->all());
        }
    }
}
