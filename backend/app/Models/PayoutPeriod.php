<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PayoutPeriod extends Model
{
    use HasFactory, HasPublicId;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_OPEN = 'open';

    public const STATUS_LOCKED = 'locked';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_OPEN,
        self::STATUS_LOCKED,
        self::STATUS_PAID,
        self::STATUS_CANCELLED,
    ];

    public const ACTIVE_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_OPEN,
        self::STATUS_LOCKED,
        self::STATUS_PAID,
    ];

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'cutoff_date',
        'payout_date',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'cutoff_date' => 'date',
            'payout_date' => 'date',
        ];
    }

    /**
     * Get the earnings many-to-many relationship for this payout period.
     *
     * This admin/internal relationship resolves multiple TeacherEarning models.
     */
    public function earnings(): BelongsToMany
    {
        return $this->belongsToMany(TeacherEarning::class, 'payout_period_teacher_earning')
            ->withTimestamps();
    }

    /**
     * Get the created by inverse relationship for this payout period.
     *
     * This admin/internal relationship resolves one User model.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the updated by inverse relationship for this payout period.
     *
     * This admin/internal relationship resolves one User model.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Determine whether this payout period can refresh earnings.
     */
    public function canRefreshEarnings(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_OPEN], true);
    }

    /**
     * Determine whether this payout period is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Scope the query to active records.
     *
     * Important query filters: status.
     *
     * @param  Builder<PayoutPeriod>  $query
     * @return Builder<PayoutPeriod>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }
}
