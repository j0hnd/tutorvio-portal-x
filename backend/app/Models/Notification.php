<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_SYSTEM = 'system';

    public const TYPE_CLASS_REMINDER = 'class_reminder';

    public const TYPE_RESCHEDULE_ALERT = 'reschedule_alert';

    public const TYPE_HOMEWORK_REMINDER = 'homework_reminder';

    public const TYPE_ADMIN_ANNOUNCEMENT = 'admin_announcement';

    public const TYPE_STUDENT_TEACHER_MESSAGE = 'student_teacher_message';

    public const TYPE_EMAIL = 'email';

    public const TYPE_IN_PORTAL = 'in_portal';

    public const TYPES = [
        self::TYPE_SYSTEM,
        self::TYPE_CLASS_REMINDER,
        self::TYPE_RESCHEDULE_ALERT,
        self::TYPE_HOMEWORK_REMINDER,
        self::TYPE_ADMIN_ANNOUNCEMENT,
        self::TYPE_STUDENT_TEACHER_MESSAGE,
        self::TYPE_EMAIL,
        self::TYPE_IN_PORTAL,
    ];

    protected $fillable = [
        'title',
        'body',
        'type',
        'sender_id',
        'scheduled_at',
        'published_at',
        'is_archived',
        'archived_at',
        'archived_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'is_archived' => 'boolean',
            'archived_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(NotificationRecipient::class);
    }
}
