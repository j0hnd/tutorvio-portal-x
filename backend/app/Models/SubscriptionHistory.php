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

    public const EVENT_REACTIVATED = 'reactivated';

    public const EVENT_UNFROZEN = self::EVENT_REACTIVATED;

    public const EVENT_STATUS_CHANGED = 'status_changed';

    public const EVENT_PAYMENT_CHANGED = 'payment_changed';

    public const EVENT_INVOICE_REFERENCE_CHANGED = 'invoice_reference_changed';

    public const EVENT_LESSONS_CONSUMED = 'lessons_consumed';

    public const EVENT_MANUAL_BALANCE_ADJUSTED = 'manual_balance_adjusted';

    public const EVENT_UPDATED = 'updated';

    public const EVENT_CANCELLED = 'cancelled';

    public const EVENT_ARCHIVED = 'archived';

    public const EVENTS = [
        self::EVENT_ASSIGNED,
        self::EVENT_RENEWED,
        self::EVENT_FROZEN,
        self::EVENT_REACTIVATED,
        self::EVENT_STATUS_CHANGED,
        self::EVENT_PAYMENT_CHANGED,
        self::EVENT_INVOICE_REFERENCE_CHANGED,
        self::EVENT_LESSONS_CONSUMED,
        self::EVENT_MANUAL_BALANCE_ADJUSTED,
        self::EVENT_UPDATED,
        self::EVENT_CANCELLED,
        self::EVENT_ARCHIVED,
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

    /**
     * Get the subscription inverse relationship for this subscription history.
     *
     * This user-facing relationship resolves one Subscription model.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Get the student inverse relationship for this subscription history.
     *
     * This user-facing relationship resolves one User model.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Get the created by inverse relationship for this subscription history.
     *
     * This admin/internal relationship resolves one User model.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
