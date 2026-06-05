<?php

namespace App\Models\Scheduling;

use App\Models\Concerns\HasPublicId;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassSchedule extends Model
{
    use HasFactory, HasPublicId;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_RESCHEDULED = 'rescheduled';

    public const STATUS_MISSED_BY_STUDENT = 'missed_by_student';

    public const STATUS_MISSED_BY_TEACHER = 'missed_by_teacher';

    public const STATUS_PENDING_CONFIRMATION = 'pending_confirmation';

    public const CLASS_TYPE_REGULAR = 'regular';

    public const CLASS_TYPE_TRIAL = 'trial';

    public const CLASS_TYPES = [
        self::CLASS_TYPE_REGULAR,
        self::CLASS_TYPE_TRIAL,
    ];

    public const STATUSES = [
        self::STATUS_SCHEDULED,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
        self::STATUS_RESCHEDULED,
        self::STATUS_MISSED_BY_STUDENT,
        self::STATUS_MISSED_BY_TEACHER,
        self::STATUS_PENDING_CONFIRMATION,
    ];

    public const BOOKED_STATUSES = [
        self::STATUS_SCHEDULED,
        self::STATUS_PENDING_CONFIRMATION,
    ];

    protected $fillable = [
        'student_id',
        'teacher_id',
        'title',
        'description',
        'status',
        'class_type',
        'timezone',
        'starts_at',
        'ends_at',
        'teacher_blocked_until',
        'meeting_url',
        'notes',
        'rescheduled_from_id',
        'created_by',
        'updated_by',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'teacher_blocked_until' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    /**
     * Get the student inverse relationship for this class schedule.
     *
     * This user-facing relationship resolves one User model.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Get the teacher inverse relationship for this class schedule.
     *
     * This user-facing relationship resolves one User model.
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Get the reminders one-to-many relationship for this class schedule.
     *
     * This user-facing relationship resolves multiple ScheduleReminder models.
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(ScheduleReminder::class);
    }

    /**
     * Get the rescheduled from inverse relationship for this class schedule.
     *
     * This user-facing relationship resolves one ClassSchedule model.
     */
    public function rescheduledFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rescheduled_from_id');
    }
}
