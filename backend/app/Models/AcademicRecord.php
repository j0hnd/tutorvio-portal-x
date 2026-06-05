<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\Scheduling\ClassSchedule;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AcademicRecord extends Model
{
    use HasFactory, HasPublicId, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUS_VOID = 'void';

    public const TYPE_PROGRESS = 'progress';

    public const TYPE_ATTENDANCE = 'attendance';

    public const TYPE_ASSESSMENT = 'assessment';

    public const TYPE_NOTE = 'note';

    public const TYPE_CERTIFICATE = 'certificate';

    public const TYPE_PLACEMENT = 'placement';

    public const TYPE_HOMEWORK = 'homework';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_ARCHIVED,
        self::STATUS_VOID,
    ];

    public const RECORD_TYPES = [
        self::TYPE_PROGRESS,
        self::TYPE_ATTENDANCE,
        self::TYPE_ASSESSMENT,
        self::TYPE_NOTE,
        self::TYPE_CERTIFICATE,
        self::TYPE_PLACEMENT,
        self::TYPE_HOMEWORK,
    ];

    protected $fillable = [
        'student_id',
        'teacher_id',
        'course_program_id',
        'lesson_id',
        'class_schedule_id',
        'record_type',
        'title',
        'description',
        'status',
        'recorded_on',
        'data',
        'recorded_by',
        'approved_by',
        'approved_at',
        'archived_by',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'recorded_on' => 'date',
            'data' => 'array',
            'approved_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
        ];
    }

    /**
     * Get the student inverse relationship for this academic record.
     *
     * This user-facing relationship resolves one User model.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Get the teacher inverse relationship for this academic record.
     *
     * This user-facing relationship resolves one User model.
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Get the course program inverse relationship for this academic record.
     *
     * This user-facing relationship resolves one CourseProgram model.
     */
    public function courseProgram(): BelongsTo
    {
        return $this->belongsTo(CourseProgram::class);
    }

    /**
     * Get the lesson inverse relationship for this academic record.
     *
     * This user-facing relationship resolves one Lesson model.
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * Get the class schedule inverse relationship for this academic record.
     *
     * This user-facing relationship resolves one ClassSchedule model.
     */
    public function classSchedule(): BelongsTo
    {
        return $this->belongsTo(ClassSchedule::class);
    }

    /**
     * Get the recorded by inverse relationship for this academic record.
     *
     * This user-facing relationship resolves one User model.
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Get the approved by inverse relationship for this academic record.
     *
     * This user-facing relationship resolves one User model.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the archived by inverse relationship for this academic record.
     *
     * This admin/internal relationship resolves one User model.
     */
    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }
}
