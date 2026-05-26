<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_STUDENT_TEACHER_MESSAGE = 'student_teacher_message';

    protected $fillable = [
        'message_thread_id',
        'sender_id',
        'body',
        'message_type',
        'sent_at',
        'edited_at',
        'archived_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'immutable_datetime',
            'edited_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(MessageThread::class, 'message_thread_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
