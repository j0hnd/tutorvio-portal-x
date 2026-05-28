<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Lesson extends Model
{
    use HasFactory;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_PENDING_CONFIRMATION = 'pending_confirmation';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_RESCHEDULED = 'rescheduled';

    public const STATUS_MISSED_BY_STUDENT = 'missed_by_student';

    public const STATUS_MISSED_BY_TEACHER = 'missed_by_teacher';

    public const STATUSES = [
        self::STATUS_SCHEDULED,
        self::STATUS_PENDING_CONFIRMATION,
        self::STATUS_COMPLETED,
        self::STATUS_EXPIRED,
        self::STATUS_CANCELLED,
        self::STATUS_RESCHEDULED,
        self::STATUS_MISSED_BY_STUDENT,
        self::STATUS_MISSED_BY_TEACHER,
    ];

    public const PROVIDER_GOOGLE_MEET = 'google_meet';

    public const PROVIDER_CUSTOM = 'custom';

    public const PROVIDER_OTHER = 'other';

    public const MEETING_PROVIDERS = [
        self::PROVIDER_GOOGLE_MEET,
        self::PROVIDER_CUSTOM,
        self::PROVIDER_OTHER,
    ];

    public const JOINABLE_STATUSES = [
        self::STATUS_SCHEDULED,
        self::STATUS_PENDING_CONFIRMATION,
    ];

    public const NOTE_REQUIRED_STATUSES = [
        self::STATUS_COMPLETED,
    ];

    public const NOTE_NOT_REQUIRED_STATUSES = [
        self::STATUS_SCHEDULED,
        self::STATUS_PENDING_CONFIRMATION,
        self::STATUS_EXPIRED,
        self::STATUS_CANCELLED,
        self::STATUS_RESCHEDULED,
        self::STATUS_MISSED_BY_STUDENT,
        self::STATUS_MISSED_BY_TEACHER,
    ];

    private const NOT_JOINABLE_REASONS = [
        self::STATUS_CANCELLED => 'lesson_cancelled',
        self::STATUS_RESCHEDULED => 'lesson_rescheduled',
        self::STATUS_MISSED_BY_STUDENT => 'lesson_not_joinable',
        self::STATUS_MISSED_BY_TEACHER => 'lesson_not_joinable',
        self::STATUS_COMPLETED => 'lesson_expired',
        self::STATUS_EXPIRED => 'lesson_expired',
    ];

    protected $fillable = [
        'student_id',
        'teacher_id',
        'start_time',
        'end_time',
        'status',
        'rescheduled_from_id',
        'notes',
        'meeting_link',
        'meeting_provider',
        'meeting_metadata',
        'join_available_from',
        'join_available_until',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'meeting_metadata' => 'array',
            'join_available_from' => 'datetime',
            'join_available_until' => 'datetime',
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

    public function rescheduledFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rescheduled_from_id');
    }

    public function replacementLesson(): HasOne
    {
        return $this->hasOne(self::class, 'rescheduled_from_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function joinAccessLogs(): HasMany
    {
        return $this->hasMany(LessonJoinAccessLog::class);
    }

    public function lessonNote(): HasOne
    {
        return $this->hasOne(LessonNote::class);
    }

    public function issueReports(): HasMany
    {
        return $this->hasMany(IssueReport::class);
    }

    public function homeworks(): HasMany
    {
        return $this->hasMany(Homework::class);
    }

    public function learningResources(): BelongsToMany
    {
        return $this->belongsToMany(LearningResource::class, 'learning_resource_lesson', 'lesson_id', 'learning_resource_id')
            ->withPivot(['assigned_by', 'assigned_at'])
            ->withTimestamps();
    }

    /**
     * @param  Builder<Lesson>  $query
     * @return Builder<Lesson>
     */
    public function scopeRequiringLessonNote(Builder $query): Builder
    {
        return $query->whereIn('status', self::NOTE_REQUIRED_STATUSES);
    }

    /**
     * @param  Builder<Lesson>  $query
     * @return Builder<Lesson>
     */
    public function scopeMissingLessonNote(Builder $query): Builder
    {
        return $query
            ->requiringLessonNote()
            ->whereDoesntHave('lessonNote', fn (Builder $query) => $query->whereNotNull('submitted_at'));
    }

    public function requiresLessonNote(): bool
    {
        return in_array($this->status, self::NOTE_REQUIRED_STATUSES, true);
    }

    public function isJoinAvailable(?CarbonInterface $now = null): bool
    {
        return $this->joinAvailability($now)['can_join'];
    }

    /**
     * @return array{
     *     can_join: bool,
     *     available_from: CarbonInterface|null,
     *     available_until: CarbonInterface|null,
     *     starts_at: CarbonInterface|null,
     *     ends_at: CarbonInterface|null,
     *     seconds_until_available: int|null,
     *     reason: string|null
     * }
     */
    public function joinAvailability(?CarbonInterface $now = null): array
    {
        $now ??= now();
        $availableFrom = $this->joinAvailableFrom();
        $availableUntil = $this->joinAvailableUntil();
        $canJoin = false;
        $reason = null;
        $secondsUntilAvailable = null;

        if (array_key_exists((string) $this->status, self::NOT_JOINABLE_REASONS)) {
            $reason = self::NOT_JOINABLE_REASONS[(string) $this->status];
        } elseif (! $this->meeting_link) {
            $reason = 'no_meeting_link';
        } elseif (! in_array($this->status, self::JOINABLE_STATUSES, true)) {
            $reason = 'lesson_not_joinable';
        } elseif ($availableFrom === null || $availableUntil === null || $this->start_time === null || $this->end_time === null) {
            $reason = 'lesson_not_joinable';
        } elseif ($now->lessThan($availableFrom)) {
            $reason = 'not_yet_available';
            $secondsUntilAvailable = max(0, $availableFrom->getTimestamp() - $now->getTimestamp());
        } elseif ($now->greaterThan($availableUntil)) {
            $reason = 'lesson_expired';
        } else {
            $canJoin = true;
            $secondsUntilAvailable = 0;
        }

        return [
            'can_join' => $canJoin,
            'available_from' => $availableFrom,
            'available_until' => $availableUntil,
            'starts_at' => $this->start_time,
            'ends_at' => $this->end_time,
            'seconds_until_available' => $secondsUntilAvailable,
            'reason' => $reason,
        ];
    }

    public function joinAvailableFrom(): ?CarbonInterface
    {
        return $this->join_available_from
            ?? $this->start_time?->copy()->subMinutes((int) config('lessons.join_window.lead_minutes', 15));
    }

    public function joinAvailableUntil(): ?CarbonInterface
    {
        return $this->join_available_until
            ?? $this->end_time?->copy()->addMinutes((int) config('lessons.join_window.grace_minutes', 15));
    }

    public function userCanJoinMeeting(?User $user, ?CarbonInterface $now = null): bool
    {
        if (! $this->userCanAccessMeeting($user) || ! $this->isJoinAvailable($now)) {
            return false;
        }

        return true;
    }

    public function userCanAccessMeeting(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasRole('admin')
            || (int) $this->student_id === (int) $user->id
            || (int) $this->teacher_id === (int) $user->id;
    }
}
