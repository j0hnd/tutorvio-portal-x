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
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicIdGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_facing_models_generate_ulid_public_ids_on_create(): void
    {
        $student = User::factory()->create();
        $teacher = User::factory()->create();

        $models = [
            'user' => User::factory()->create(),
            'lesson' => Lesson::create([
                'student_id' => $student->id,
                'teacher_id' => $teacher->id,
                'start_time' => '2026-06-10 09:00:00',
                'end_time' => '2026-06-10 10:00:00',
                'status' => Lesson::STATUS_SCHEDULED,
            ]),
            'invoice' => Invoice::factory()->create(),
            'subscription' => Subscription::factory()->create(),
            'homework' => Homework::factory()->create(),
            'message_thread' => MessageThread::create([
                'title' => 'Lesson follow-up',
                'thread_type' => MessageThread::TYPE_STUDENT_TEACHER,
                'status' => MessageThread::STATUS_ACTIVE,
                'student_id' => $student->id,
                'teacher_id' => $teacher->id,
                'created_by' => $student->id,
            ]),
            'learning_resource' => LearningResource::create([
                'title' => 'Business English worksheet',
                'resource_type' => LearningResource::TYPE_WORKSHEET,
                'created_by' => $teacher->id,
            ]),
            'course_program' => CourseProgram::factory()->create(),
            'announcement' => Announcement::create([
                'title' => 'Portal maintenance',
                'body' => 'The portal will be unavailable briefly.',
                'status' => Announcement::STATUS_DRAFT,
                'type' => Announcement::TYPE_ADMIN_ANNOUNCEMENT,
                'author_id' => $teacher->id,
            ]),
            'notification' => Notification::create([
                'title' => 'Homework reminder',
                'body' => 'A homework deadline is approaching.',
                'type' => Notification::TYPE_HOMEWORK_REMINDER,
                'sender_id' => $teacher->id,
            ]),
            'audit_log' => AuditLog::factory()->create(),
        ];

        foreach ($models as $name => $model) {
            $this->assertNotNull($model->public_id, "{$name} public_id was not generated.");
            $this->assertTrue(Str::isUlid($model->public_id), "{$name} public_id is not a ULID.");
        }
    }

    public function test_existing_public_id_is_not_overwritten_on_create(): void
    {
        $publicId = (string) Str::ulid();

        $user = User::factory()->make();
        $user->public_id = $publicId;
        $user->save();

        $this->assertSame($publicId, $user->public_id);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'public_id' => $publicId,
        ]);
    }

    public function test_public_id_is_not_used_as_the_default_route_key(): void
    {
        $this->assertSame('id', (new User)->getRouteKeyName());
    }
}
