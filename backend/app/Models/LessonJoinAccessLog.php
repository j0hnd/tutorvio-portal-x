<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonJoinAccessLog extends Model
{
    use HasFactory;

    public const RESULT_ALLOWED = 'allowed';

    public const RESULT_DENIED = 'denied';

    public const RESULT_NOT_YET_AVAILABLE = 'not_yet_available';

    public const RESULT_EXPIRED = 'expired';

    public const RESULT_CANCELLED = 'cancelled';

    public const RESULT_RESCHEDULED = 'rescheduled';

    protected $fillable = [
        'lesson_id',
        'user_id',
        'user_role',
        'access_result',
        'reason',
        'ip_address',
        'user_agent',
        'accessed_at',
    ];

    protected function casts(): array
    {
        return [
            'accessed_at' => 'datetime',
        ];
    }

    /**
     * Get the lesson inverse relationship for this lesson join access log.
     *
     * This user-facing relationship resolves one Lesson model.
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * Get the user inverse relationship for this lesson join access log.
     *
     * This user-facing relationship resolves one User model.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
