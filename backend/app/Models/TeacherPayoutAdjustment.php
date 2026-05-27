<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherPayoutAdjustment extends Model
{
    use HasFactory;

    public const TYPE_BONUS = 'bonus';

    public const TYPE_DEDUCTION = 'deduction';

    public const TYPE_CORRECTION = 'correction';

    public const TYPE_REIMBURSEMENT = 'reimbursement';

    public const TYPE_OTHER = 'other';

    public const TYPES = [
        self::TYPE_BONUS,
        self::TYPE_DEDUCTION,
        self::TYPE_CORRECTION,
        self::TYPE_REIMBURSEMENT,
        self::TYPE_OTHER,
    ];

    protected $fillable = [
        'teacher_id',
        'payout_period_id',
        'type',
        'amount',
        'currency',
        'reason',
        'internal_notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function payoutPeriod(): BelongsTo
    {
        return $this->belongsTo(PayoutPeriod::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
