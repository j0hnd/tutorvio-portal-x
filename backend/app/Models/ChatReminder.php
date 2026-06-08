<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatReminder extends Model
{
    use HasFactory;

    public const TYPE_UPCOMING_LESSON = 'upcoming_lesson';

    public const TYPE_HOMEWORK_DUE = 'homework_due';

    public const TYPE_MISSED_CLASS_FOLLOW_UP = 'missed_class_follow_up';

    public const TYPE_PENDING_TEACHER_NOTE = 'pending_teacher_note';

    public const TYPE_RESCHEDULE_CONFIRMATION = 'reschedule_confirmation';

    public const TYPES = [
        self::TYPE_UPCOMING_LESSON,
        self::TYPE_HOMEWORK_DUE,
        self::TYPE_MISSED_CLASS_FOLLOW_UP,
        self::TYPE_PENDING_TEACHER_NOTE,
        self::TYPE_RESCHEDULE_CONFIRMATION,
    ];

    public const STATUS_SENT = 'sent';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUSES = [
        self::STATUS_SENT,
        self::STATUS_SKIPPED,
    ];

    protected $fillable = [
        'conversation_id',
        'conversation_message_id',
        'type',
        'source_type',
        'source_id',
        'dedupe_key',
        'status',
        'recipient_user_ids',
        'sent_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'recipient_user_ids' => 'array',
            'sent_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the conversation inverse relationship for this chat reminder.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the generated chat message inverse relationship.
     */
    public function conversationMessage(): BelongsTo
    {
        return $this->belongsTo(ConversationMessage::class);
    }
}
