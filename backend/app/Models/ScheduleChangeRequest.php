<?php

namespace App\Models;

use App\Models\Scheduling\ClassSchedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScheduleChangeRequest extends Model
{
    use HasFactory, SoftDeletes;

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
        'requester_id',
        'student_id',
        'teacher_id',
        'lesson_id',
        'class_schedule_id',
        'current_starts_at',
        'current_ends_at',
        'requested_starts_at',
        'requested_ends_at',
        'timezone',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected function casts(): array
    {
        return [
            'current_starts_at' => 'immutable_datetime',
            'current_ends_at' => 'immutable_datetime',
            'requested_starts_at' => 'immutable_datetime',
            'requested_ends_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function classSchedule(): BelongsTo
    {
        return $this->belongsTo(ClassSchedule::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @param  Builder<ScheduleChangeRequest>  $query
     * @return Builder<ScheduleChangeRequest>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
