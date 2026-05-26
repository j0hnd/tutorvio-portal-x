<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasFactory;

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
