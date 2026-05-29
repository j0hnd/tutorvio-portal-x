<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasFactory, HasPublicId;

    public const TYPE_PACKAGE = 'package';

    public const TYPE_SUBSCRIPTION = 'subscription';

    public const TYPES = [
        self::TYPE_PACKAGE,
        self::TYPE_SUBSCRIPTION,
    ];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_EXPIRED,
        self::STATUS_CANCELLED,
    ];

    public const PAYMENT_STATUS_PAID = 'paid';

    public const PAYMENT_STATUS_UNPAID = 'unpaid';

    public const PAYMENT_STATUS_PARTIAL = 'partial';

    public const PAYMENT_STATUS_OVERDUE = 'overdue';

    public const PAYMENT_STATUSES = [
        self::PAYMENT_STATUS_PAID,
        self::PAYMENT_STATUS_UNPAID,
        self::PAYMENT_STATUS_PARTIAL,
        self::PAYMENT_STATUS_OVERDUE,
    ];

    public const RENEWAL_REMINDER_STATUS_NONE = 'none';

    public const RENEWAL_REMINDER_STATUS_PENDING = 'pending';

    public const RENEWAL_REMINDER_STATUS_SENT = 'sent';

    public const RENEWAL_REMINDER_STATUS_NOT_ELIGIBLE = 'not_eligible';

    public const RENEWAL_REMINDER_STATUSES = [
        self::RENEWAL_REMINDER_STATUS_NONE,
        self::RENEWAL_REMINDER_STATUS_PENDING,
        self::RENEWAL_REMINDER_STATUS_SENT,
        self::RENEWAL_REMINDER_STATUS_NOT_ELIGIBLE,
    ];

    protected $fillable = [
        'user_id',
        'plan_name',
        'package_type',
        'total_lesson_count',
        'consumed_lesson_count',
        'remaining_lesson_count',
        'status',
        'is_frozen',
        'frozen_at',
        'payment_status',
        'invoice_id',
        'invoice_reference',
        'internal_notes',
        'renewed_from_subscription_id',
        'renewal_reminder_due_at',
        'renewal_reminder_last_sent_at',
        'renewal_reminder_status',
        'renewal_reminder_window_key',
        'renewal_eligible',
        'renewal_reminder_notes',
        'created_by',
        'updated_by',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'total_lesson_count' => 'integer',
            'consumed_lesson_count' => 'integer',
            'remaining_lesson_count' => 'integer',
            'is_frozen' => 'boolean',
            'frozen_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'renewal_reminder_due_at' => 'datetime',
            'renewal_reminder_last_sent_at' => 'datetime',
            'renewal_eligible' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function renewedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'renewed_from_subscription_id');
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(self::class, 'renewed_from_subscription_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(SubscriptionHistory::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
