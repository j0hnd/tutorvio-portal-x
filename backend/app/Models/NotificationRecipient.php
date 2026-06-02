<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationRecipient extends Model
{
    use HasFactory;

    public const CHANNEL_IN_PORTAL = 'in_portal';

    public const CHANNEL_EMAIL = 'email';

    public const CHANNELS = [
        self::CHANNEL_IN_PORTAL,
        self::CHANNEL_EMAIL,
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_FAILED = 'failed';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_SENT,
        self::STATUS_DELIVERED,
        self::STATUS_FAILED,
    ];

    protected $fillable = [
        'notification_id',
        'user_id',
        'channel',
        'delivery_status',
        'sent_at',
        'delivered_at',
        'read_at',
        'archived_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'read_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
