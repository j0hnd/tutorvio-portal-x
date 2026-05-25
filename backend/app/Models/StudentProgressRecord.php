<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentProgressRecord extends Model
{
    use HasFactory;

    public const SKILL_SPEAKING = 'speaking';

    public const SKILL_LISTENING = 'listening';

    public const SKILL_VOCABULARY = 'vocabulary';

    public const SKILL_GRAMMAR = 'grammar';

    public const SKILL_PRONUNCIATION = 'pronunciation';

    public const SKILL_CONFIDENCE = 'confidence';

    public const SKILL_FLUENCY = 'fluency';

    public const SKILL_WORKPLACE_COMMUNICATION = 'workplace_communication';

    public const RATING_NEEDS_SUPPORT = 'needs_support';

    public const RATING_DEVELOPING = 'developing';

    public const RATING_CONFIDENT = 'confident';

    public const RATING_STRONG = 'strong';

    public const STATUS_NOT_STARTED = 'not_started';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_NEEDS_SUPPORT = 'needs_support';

    public const LEVEL_MOVEMENT_UP = 'moved_up';

    public const LEVEL_MOVEMENT_MAINTAINED = 'maintained';

    public const LEVEL_MOVEMENT_DOWN = 'moved_down';

    public const LEVEL_MOVEMENT_NEEDS_REVIEW = 'needs_review';

    public const SKILL_AREAS = [
        self::SKILL_SPEAKING,
        self::SKILL_LISTENING,
        self::SKILL_VOCABULARY,
        self::SKILL_GRAMMAR,
        self::SKILL_PRONUNCIATION,
        self::SKILL_CONFIDENCE,
        self::SKILL_FLUENCY,
        self::SKILL_WORKPLACE_COMMUNICATION,
    ];

    public const RATINGS = [
        self::RATING_NEEDS_SUPPORT,
        self::RATING_DEVELOPING,
        self::RATING_CONFIDENT,
        self::RATING_STRONG,
    ];

    public const STATUSES = [
        self::STATUS_NOT_STARTED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_NEEDS_SUPPORT,
    ];

    public const LEVEL_MOVEMENTS = [
        self::LEVEL_MOVEMENT_UP,
        self::LEVEL_MOVEMENT_MAINTAINED,
        self::LEVEL_MOVEMENT_DOWN,
        self::LEVEL_MOVEMENT_NEEDS_REVIEW,
    ];

    protected $fillable = [
        'student_id',
        'teacher_id',
        'skill_area',
        'progress_summary_by_skill',
        'speaking_confidence_rating',
        'vocabulary_progress',
        'grammar_development',
        'pronunciation_progress',
        'lesson_completion_count',
        'teacher_comments',
        'milestone_achievements',
        'level_movement',
        'goals_completed',
        'goals_in_progress',
        'progress_status',
        'recorded_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'progress_summary_by_skill' => 'array',
            'milestone_achievements' => 'array',
            'goals_completed' => 'array',
            'goals_in_progress' => 'array',
            'lesson_completion_count' => 'integer',
            'recorded_at' => 'immutable_datetime',
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
