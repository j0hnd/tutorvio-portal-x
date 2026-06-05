<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conversation extends Model
{
    use HasFactory, HasPublicId, SoftDeletes;

    public const TYPE_STUDENT_TEACHER = 'student_teacher';

    public const TYPE_TEACHER_ADMIN = 'teacher_admin';

    public const TYPE_ADMIN_STUDENT = 'admin_student';

    public const TYPE_GROUP_COURSE = 'group_course';

    public const TYPE_ANNOUNCEMENT_THREAD = 'announcement_thread';

    public const TYPES = [
        self::TYPE_STUDENT_TEACHER,
        self::TYPE_TEACHER_ADMIN,
        self::TYPE_ADMIN_STUDENT,
        self::TYPE_GROUP_COURSE,
        self::TYPE_ANNOUNCEMENT_THREAD,
    ];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_ARCHIVED,
        self::STATUS_CLOSED,
    ];

    protected $fillable = [
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
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'immutable_datetime',
            'last_message_metadata' => 'array',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the student inverse relationship for this conversation.
     *
     * This user-facing relationship resolves one User model.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Get the teacher inverse relationship for this conversation.
     *
     * This user-facing relationship resolves one User model.
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Get the course program inverse relationship for this conversation.
     *
     * This user-facing relationship resolves one CourseProgram model.
     */
    public function courseProgram(): BelongsTo
    {
        return $this->belongsTo(CourseProgram::class);
    }

    /**
     * Get the created by inverse relationship for this conversation.
     *
     * This admin/internal relationship resolves one User model.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the last message sender inverse relationship for this conversation.
     *
     * This user-facing relationship resolves one User model.
     */
    public function lastMessageBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_message_by');
    }

    /**
     * Get the student profile relationship for this conversation.
     *
     * This user-facing relationship resolves one StudentProfile model.
     */
    public function studentProfile(): HasOne
    {
        return $this->hasOne(StudentProfile::class, 'user_id', 'student_id');
    }

    /**
     * Get the teacher profile relationship for this conversation.
     *
     * This user-facing relationship resolves one TeacherProfile model.
     */
    public function teacherProfile(): HasOne
    {
        return $this->hasOne(TeacherProfile::class, 'user_id', 'teacher_id');
    }

    /**
     * Get the participants one-to-many relationship for this conversation.
     *
     * This user-facing relationship resolves multiple ConversationParticipant models.
     */
    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    /**
     * Get the messages one-to-many relationship for this conversation.
     *
     * This user-facing relationship resolves public chat messages.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class);
    }

    /**
     * Get the users many-to-many relationship for this conversation.
     *
     * This user-facing relationship resolves multiple User models.
     * Important query filters: pivot deleted_at.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot([
                'participant_role',
                'participant_role_snapshot',
                'participant_roles_snapshot',
                'joined_at',
                'last_read_at',
                'last_read_message_id',
                'muted_at',
                'archived_at',
                'metadata',
                'deleted_at',
            ])
            ->wherePivotNull('deleted_at')
            ->withTimestamps();
    }
}
