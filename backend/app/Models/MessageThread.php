<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class MessageThread extends Model
{
    use HasFactory, HasPublicId, SoftDeletes;

    public const TYPE_STUDENT_TEACHER = 'student_teacher';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_CLOSED,
    ];

    protected $fillable = [
        'title',
        'thread_type',
        'status',
        'student_id',
        'teacher_id',
        'created_by',
        'last_message_at',
        'is_archived',
        'archived_at',
        'archived_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'immutable_datetime',
            'is_archived' => 'boolean',
            'archived_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the student inverse relationship for this message thread.
     *
     * This user-facing relationship resolves one User model.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Get the teacher inverse relationship for this message thread.
     *
     * This user-facing relationship resolves one User model.
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Get the created by inverse relationship for this message thread.
     *
     * This admin/internal relationship resolves one User model.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the archived by inverse relationship for this message thread.
     *
     * This admin/internal relationship resolves one User model.
     */
    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    /**
     * Get the participants one-to-many relationship for this message thread.
     *
     * This user-facing relationship resolves multiple MessageThreadParticipant models.
     */
    public function participants(): HasMany
    {
        return $this->hasMany(MessageThreadParticipant::class);
    }

    /**
     * Get the messages one-to-many relationship for this message thread.
     *
     * This user-facing relationship resolves multiple Message models.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Get the latest message one-to-one relationship for this message thread.
     *
     * This user-facing relationship resolves one Message model.
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany('sent_at');
    }
}
