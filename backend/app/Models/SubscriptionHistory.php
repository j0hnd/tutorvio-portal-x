<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionHistory extends Model
{
    use HasFactory;

    public const EVENT_ASSIGNED = 'assigned';

    public const EVENT_RENEWED = 'renewed';

    public const EVENT_FROZEN = 'frozen';

    public const EVENT_UNFROZEN = 'unfrozen';

    public const EVENT_STATUS_CHANGED = 'status_changed';

    public const EVENT_PAYMENT_CHANGED = 'payment_changed';

    public const EVENT_LESSONS_CONSUMED = 'lessons_consumed';

    public const EVENTS = [
        self::EVENT_ASSIGNED,
        self::EVENT_RENEWED,
        self::EVENT_FROZEN,
        self::EVENT_UNFROZEN,
        self::EVENT_STATUS_CHANGED,
        self::EVENT_PAYMENT_CHANGED,
        self::EVENT_LESSONS_CONSUMED,
    ];

    protected $fillable = [
        'subscription_id',
        'student_id',
        'event_type',
        'plan_name',
        'package_type',
        'total_lesson_count',
        'consumed_lesson_count',
        'remaining_lesson_count',
        'status',
        'is_frozen',
        'payment_status',
        'starts_at',
        'ends_at',
        'previous_values',
        'new_values',
        'notes',
        'effective_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'total_lesson_count' => 'integer',
            'consumed_lesson_count' => 'integer',
            'remaining_lesson_count' => 'integer',
            'is_frozen' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'previous_values' => 'array',
            'new_values' => 'array',
            'effective_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
