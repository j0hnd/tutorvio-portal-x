<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageThreadParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_thread_id',
        'user_id',
        'participant_role',
        'last_read_at',
        'muted_at',
        'archived_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'last_read_at' => 'immutable_datetime',
            'muted_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(MessageThread::class, 'message_thread_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
