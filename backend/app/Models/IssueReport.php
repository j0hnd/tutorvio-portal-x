<?php

namespace App\Models;

use App\Models\Concerns\AppliesFullTextSearch;
use App\Models\Concerns\HasPublicId;
use App\Models\Scheduling\ClassSchedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IssueReport extends Model
{
    use AppliesFullTextSearch, HasFactory, HasPublicId, SoftDeletes;

    public const TYPE_TECHNICAL_ISSUE = 'technical_issue';

    public const TYPE_STUDENT_CONCERN = 'student_concern';

    public const TYPE_TEACHER_CONCERN = 'teacher_concern';

    public const TYPE_CLASS_INCIDENT = 'class_incident';

    public const TYPE_STUDENT_ABSENT = 'student_absent';

    public const TYPE_TEACHER_ABSENT = 'teacher_absent';

    public const TYPE_STUDENT_ABSENT_ISSUE_FORM = 'student_absent_issue_form';

    public const TYPE_TEACHER_ABSENT_ISSUE_FORM = 'teacher_absent_issue_form';

    public const TYPE_MATERIAL_REQUEST = 'material_request';

    public const TYPE_CHANGE_REQUEST = 'change_request';

    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_CANCELLED = 'cancelled';

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    public const ISSUE_TYPES = [
        self::TYPE_TECHNICAL_ISSUE,
        self::TYPE_STUDENT_CONCERN,
        self::TYPE_TEACHER_CONCERN,
        self::TYPE_CLASS_INCIDENT,
        self::TYPE_STUDENT_ABSENT_ISSUE_FORM,
        self::TYPE_TEACHER_ABSENT_ISSUE_FORM,
        self::TYPE_MATERIAL_REQUEST,
        self::TYPE_CHANGE_REQUEST,
    ];

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_IN_PROGRESS,
        self::STATUS_RESOLVED,
        self::STATUS_CLOSED,
        self::STATUS_CANCELLED,
    ];

    public const PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_NORMAL,
        self::PRIORITY_HIGH,
        self::PRIORITY_URGENT,
    ];

    protected $fillable = [
        'issue_type',
        'status',
        'priority',
        'reporter_id',
        'target_user_id',
        'lesson_id',
        'class_schedule_id',
        'related_student_id',
        'related_teacher_id',
        'course_program_id',
        'material_id',
        'learning_resource_id',
        'assigned_to_id',
        'title',
        'description',
        'resolution_notes',
        'resolved_at',
        'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'immutable_datetime',
        ];
    }

    /**
     * Scope the query to searchable issue report text.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $this->applyFullTextSearch($query, ['title', 'description', 'resolution_notes'], $term);
    }

    /**
     * Get the reporter inverse relationship for this issue report.
     *
     * This user-facing relationship resolves one User model.
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * Get the target user inverse relationship for this issue report.
     *
     * This user-facing relationship resolves one User model.
     */
    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    /**
     * Get the lesson inverse relationship for this issue report.
     *
     * This user-facing relationship resolves one Lesson model.
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * Get the class schedule inverse relationship for this issue report.
     *
     * This user-facing relationship resolves one ClassSchedule model.
     */
    public function classSchedule(): BelongsTo
    {
        return $this->belongsTo(ClassSchedule::class);
    }

    /**
     * Get the related student inverse relationship for this issue report.
     *
     * This user-facing relationship resolves one User model.
     */
    public function relatedStudent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'related_student_id');
    }

    /**
     * Get the related teacher inverse relationship for this issue report.
     *
     * This user-facing relationship resolves one User model.
     */
    public function relatedTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'related_teacher_id');
    }

    /**
     * Get the course program inverse relationship for this issue report.
     *
     * This user-facing relationship resolves one CourseProgram model.
     */
    public function courseProgram(): BelongsTo
    {
        return $this->belongsTo(CourseProgram::class);
    }

    /**
     * Get the material inverse relationship for this issue report.
     *
     * This user-facing relationship resolves one Material model.
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /**
     * Get the learning resource inverse relationship for this issue report.
     *
     * This admin/internal relationship resolves one LearningResource model.
     */
    public function learningResource(): BelongsTo
    {
        return $this->belongsTo(LearningResource::class);
    }

    /**
     * Get the assigned to inverse relationship for this issue report.
     *
     * This user-facing relationship resolves one User model.
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    /**
     * Get the resolved by inverse relationship for this issue report.
     *
     * This admin/internal relationship resolves one User model.
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Get the comments one-to-many relationship for this issue report.
     *
     * This user-facing relationship resolves multiple IssueComment models.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(IssueComment::class);
    }

    /**
     * Scope the query to open records.
     *
     * Important query filters: status.
     *
     * @param  Builder<IssueReport>  $query
     * @return Builder<IssueReport>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_OPEN,
            self::STATUS_IN_PROGRESS,
        ]);
    }
}
