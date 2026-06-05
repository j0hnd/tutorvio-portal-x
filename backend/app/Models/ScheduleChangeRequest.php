<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\Scheduling\ClassSchedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScheduleChangeRequest extends Model
{
    use HasFactory, HasPublicId, SoftDeletes;

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

    /**
     * Get the requester inverse relationship for this schedule change request.
     *
     * This user-facing relationship resolves one User model.
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * Get the student inverse relationship for this schedule change request.
     *
     * This user-facing relationship resolves one User model.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Get the teacher inverse relationship for this schedule change request.
     *
     * This user-facing relationship resolves one User model.
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Get the lesson inverse relationship for this schedule change request.
     *
     * This user-facing relationship resolves one Lesson model.
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * Get the class schedule inverse relationship for this schedule change request.
     *
     * This user-facing relationship resolves one ClassSchedule model.
     */
    public function classSchedule(): BelongsTo
    {
        return $this->belongsTo(ClassSchedule::class);
    }

    /**
     * Get the reviewer inverse relationship for this schedule change request.
     *
     * This admin/internal relationship resolves one User model.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Scope the query to pending records.
     *
     * Important query filters: status.
     *
     * @param  Builder<ScheduleChangeRequest>  $query
     * @return Builder<ScheduleChangeRequest>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
