<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LessonRecord extends Model
{
    use HasFactory;

    public const TYPE_TRIAL_CLASS = 'trial_class';

    public const TYPE_FIRST_OFFICIAL_LESSON = 'first_official_lesson';

    public const TYPE_PRACTICAL_CONVERSATIONAL_ENGLISH = 'practical_conversational_english';

    public const TYPE_BUSINESS_ENGLISH = 'business_english';

    public const TYPE_EXAM_PREPARATION = 'exam_preparation';

    public const TYPE_SPECIAL_ADVANCED_COURSES = 'special_advanced_courses';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_RESCHEDULED = 'rescheduled';

    public const STATUS_MISSED_BY_STUDENT = 'missed_by_student';

    public const STATUS_MISSED_BY_TEACHER = 'missed_by_teacher';

    public const STATUS_PENDING_CONFIRMATION = 'pending_confirmation';

    public const ATTENDANCE_PRESENT = 'present';

    public const ATTENDANCE_ABSENT = 'absent';

    public const ATTENDANCE_LATE = 'late';

    public const ATTENDANCE_EXCUSED = 'excused';

    public const ATTENDANCE_NO_SHOW = 'no_show';

    public const PROVIDER_GOOGLE_MEET = 'google_meet';

    public const PROVIDER_CUSTOM = 'custom';

    public const PROVIDER_OTHER = 'other';

    public const LESSON_TYPES = [
        self::TYPE_TRIAL_CLASS,
        self::TYPE_FIRST_OFFICIAL_LESSON,
        self::TYPE_PRACTICAL_CONVERSATIONAL_ENGLISH,
        self::TYPE_BUSINESS_ENGLISH,
        self::TYPE_EXAM_PREPARATION,
        self::TYPE_SPECIAL_ADVANCED_COURSES,
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

    public const ATTENDANCE_STATUSES = [
        self::ATTENDANCE_PRESENT,
        self::ATTENDANCE_ABSENT,
        self::ATTENDANCE_LATE,
        self::ATTENDANCE_EXCUSED,
        self::ATTENDANCE_NO_SHOW,
    ];

    public const MEETING_PROVIDERS = [
        self::PROVIDER_GOOGLE_MEET,
        self::PROVIDER_CUSTOM,
        self::PROVIDER_OTHER,
    ];

    protected $fillable = [
        'student_id',
        'teacher_id',
        'scheduled_date',
        'start_time',
        'end_time',
        'meeting_link',
        'meeting_provider',
        'meeting_metadata',
        'join_available_from',
        'join_available_until',
        'lesson_type',
        'lesson_status',
        'lesson_notes',
        'homework_details',
        'homework_due_date',
        'attendance_status',
        'is_completed',
        'completed_at',
        'completed_by',
        'internal_remarks',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'homework_due_date' => 'date',
            'is_completed' => 'boolean',
            'meeting_metadata' => 'array',
            'join_available_from' => 'immutable_datetime',
            'join_available_until' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function materials(): BelongsToMany
    {
        return $this->belongsToMany(Material::class, 'lesson_record_materials')
            ->withTimestamps();
    }

    public function lessonNote(): HasOne
    {
        return $this->hasOne(LessonNote::class);
    }

    public function isJoinAvailable(?CarbonInterface $now = null): bool
    {
        if (! $this->meeting_link || ! in_array($this->lesson_status, [self::STATUS_SCHEDULED, self::STATUS_PENDING_CONFIRMATION], true)) {
            return false;
        }

        $now ??= now();
        $availableFrom = $this->joinAvailableFrom();
        $availableUntil = $this->joinAvailableUntil();

        return $availableFrom !== null
            && $availableUntil !== null
            && $now->greaterThanOrEqualTo($availableFrom)
            && $now->lessThanOrEqualTo($availableUntil);
    }

    public function userCanJoinMeeting(?User $user, ?CarbonInterface $now = null): bool
    {
        if (! $user || ! $this->isJoinAvailable($now)) {
            return false;
        }

        return $user->hasAnyRole(['admin', 'staff'])
            || (int) $this->student_id === (int) $user->id
            || (int) $this->teacher_id === (int) $user->id;
    }

    public function joinAvailableFrom(): ?CarbonInterface
    {
        return $this->join_available_from
            ?? $this->scheduledDateTime($this->start_time)?->subMinutes((int) config('lessons.join_window.lead_minutes', 15));
    }

    public function joinAvailableUntil(): ?CarbonInterface
    {
        return $this->join_available_until
            ?? $this->scheduledDateTime($this->end_time)?->addMinutes((int) config('lessons.join_window.grace_minutes', 15));
    }

    private function scheduledDateTime(?string $time): ?CarbonImmutable
    {
        if ($this->scheduled_date === null || $time === null) {
            return null;
        }

        return CarbonImmutable::parse($this->scheduled_date->toDateString().' '.$time);
    }
}
