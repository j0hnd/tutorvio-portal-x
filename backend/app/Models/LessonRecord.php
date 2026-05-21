<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    protected $fillable = [
        'student_id',
        'teacher_id',
        'scheduled_date',
        'start_time',
        'end_time',
        'meeting_link',
        'lesson_type',
        'lesson_status',
        'lesson_notes',
        'homework_details',
        'is_completed',
        'completed_at',
        'completed_by',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'is_completed' => 'boolean',
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
}
