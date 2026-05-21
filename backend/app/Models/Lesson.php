<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lesson extends Model
{
    use HasFactory;

    public const PROVIDER_GOOGLE_MEET = 'google_meet';

    public const PROVIDER_CUSTOM = 'custom';

    public const PROVIDER_OTHER = 'other';

    public const MEETING_PROVIDERS = [
        self::PROVIDER_GOOGLE_MEET,
        self::PROVIDER_CUSTOM,
        self::PROVIDER_OTHER,
    ];

    protected $fillable = [
        'student_id',
        'teacher_id',
        'start_time',
        'end_time',
        'status',
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

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function isJoinAvailable(?CarbonInterface $now = null): bool
    {
        if (! $this->meeting_link || ! in_array($this->status, ['scheduled', 'pending_confirmation'], true)) {
            return false;
        }

        $now ??= now();
        $availableFrom = $this->join_available_from ?? $this->start_time;
        $availableUntil = $this->join_available_until ?? $this->end_time;

        return $availableFrom !== null
            && $availableUntil !== null
            && $now->greaterThanOrEqualTo($availableFrom)
            && $now->lessThanOrEqualTo($availableUntil);
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
