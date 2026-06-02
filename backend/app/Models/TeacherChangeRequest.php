<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherChangeRequest extends Model
{
    use HasFactory, HasPublicId;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'student_id',
        'current_teacher_id',
        'approved_teacher_id',
        'requested_reason',
        'preferred_schedule_notes',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_reason',
        'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function currentTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_teacher_id');
    }

    public function approvedTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_teacher_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @param  Builder<TeacherChangeRequest>  $query
     * @return Builder<TeacherChangeRequest>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
