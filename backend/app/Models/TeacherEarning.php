<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TeacherEarning extends Model
{
    use HasFactory;

    public const SOURCE_LESSON_RECORD = 'lesson_record';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_INCLUDED_IN_PAYOUT = 'included_in_payout';

    public const STATUS_PAID = 'paid';

    public const STATUS_VOIDED = 'voided';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_INCLUDED_IN_PAYOUT,
        self::STATUS_PAID,
        self::STATUS_VOIDED,
    ];

    protected $fillable = [
        'teacher_id',
        'lesson_record_id',
        'source_type',
        'source_id',
        'pay_model',
        'rate_used',
        'quantity',
        'amount',
        'currency',
        'calculation_metadata',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'rate_used' => 'decimal:2',
            'quantity' => 'decimal:2',
            'amount' => 'decimal:2',
            'calculation_metadata' => 'array',
        ];
    }

    /**
     * Get the teacher inverse relationship for this teacher earning.
     *
     * This user-facing relationship resolves one User model.
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Get the lesson record inverse relationship for this teacher earning.
     *
     * This admin/internal relationship resolves one LessonRecord model.
     */
    public function lessonRecord(): BelongsTo
    {
        return $this->belongsTo(LessonRecord::class);
    }

    /**
     * Get the payout periods many-to-many relationship for this teacher earning.
     *
     * This admin/internal relationship resolves multiple PayoutPeriod models.
     */
    public function payoutPeriods(): BelongsToMany
    {
        return $this->belongsToMany(PayoutPeriod::class, 'payout_period_teacher_earning')
            ->withTimestamps();
    }
}
