<?php

namespace App\Models\Scheduling;

use App\Models\Concerns\HasPublicId;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleReminder extends Model
{
    use HasFactory, HasPublicId;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_FAILED = 'failed';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_SENDING,
        self::STATUS_SENT,
        self::STATUS_CANCELLED,
        self::STATUS_FAILED,
    ];

    public const CHANNELS = [
        'email',
        'sms',
        'push',
        'in_app',
    ];

    protected $fillable = [
        'class_schedule_id',
        'user_id',
        'channel',
        'status',
        'scheduled_for',
        'sent_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function classSchedule(): BelongsTo
    {
        return $this->belongsTo(ClassSchedule::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
