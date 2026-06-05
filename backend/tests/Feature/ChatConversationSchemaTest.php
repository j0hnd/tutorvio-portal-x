<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\CourseProgram;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ChatConversationSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversation_tables_expose_required_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('conversations', [
            'public_id',
            'type',
            'title',
            'status',
            'student_id',
            'teacher_id',
            'course_program_id',
            'created_by',
            'last_message_at',
            'last_message_by',
            'last_message_preview',
            'last_message_metadata',
            'metadata',
            'created_at',
            'updated_at',
            'deleted_at',
        ]));

        $this->assertTrue(Schema::hasColumns('conversation_participants', [
            'conversation_id',
            'user_id',
            'participant_role',
            'participant_role_snapshot',
            'participant_roles_snapshot',
            'joined_at',
            'last_read_at',
            'last_read_message_id',
            'muted_at',
            'archived_at',
            'metadata',
            'created_at',
            'updated_at',
            'deleted_at',
        ]));
    }

    public function test_conversation_relationships_resolve_chat_context(): void
    {
        $admin = User::factory()->create();
        $student = User::factory()->create();
        $teacher = User::factory()->create();
        $courseProgram = CourseProgram::factory()->create(['created_by' => $admin->id]);

        StudentProfile::query()->create([
            'user_id' => $student->id,
            'assigned_teacher_id' => $teacher->id,
        ]);

        TeacherProfile::query()->create([
            'user_id' => $teacher->id,
            'specialization' => 'English',
        ]);

        $conversation = Conversation::query()->create([
            'type' => Conversation::TYPE_GROUP_COURSE,
            'title' => 'B1 course discussion',
            'status' => Conversation::STATUS_ACTIVE,
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'course_program_id' => $courseProgram->id,
            'created_by' => $admin->id,
            'last_message_by' => $teacher->id,
            'last_message_at' => now(),
            'last_message_preview' => 'Welcome to class.',
            'last_message_metadata' => ['message_type' => 'text'],
        ]);

        $participant = ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $student->id,
            'participant_role' => 'student',
            'participant_role_snapshot' => 'student',
            'participant_roles_snapshot' => ['student'],
            'joined_at' => now(),
        ]);

        $this->assertNotNull($conversation->public_id);
        $this->assertSame($student->id, $conversation->student->id);
        $this->assertSame($teacher->id, $conversation->teacher->id);
        $this->assertSame($courseProgram->id, $conversation->courseProgram->id);
        $this->assertSame($admin->id, $conversation->createdBy->id);
        $this->assertSame($teacher->id, $conversation->lastMessageBy->id);
        $this->assertSame($student->id, $conversation->studentProfile->user_id);
        $this->assertSame($teacher->id, $conversation->teacherProfile->user_id);
        $this->assertSame($participant->id, $conversation->participants->first()->id);
        $this->assertSame($conversation->id, $participant->conversation->id);
        $this->assertSame($student->id, $participant->user->id);
        $this->assertSame($student->id, $student->conversationParticipants->first()->user_id);
        $this->assertSame($student->id, $conversation->users->first()->id);
        $this->assertSame($conversation->id, $student->conversations->first()->id);
        $this->assertSame($conversation->id, $courseProgram->conversations->first()->id);
    }
}
