<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Homework extends Model
{
    use HasFactory;

    protected $table = 'homeworks';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUSES = [
        self::STATUS_ASSIGNED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_REVIEWED,
        self::STATUS_OVERDUE,
    ];

    protected $fillable = [
        'lesson_id',
        'student_id',
        'teacher_id',
        'title',
        'instructions',
        'due_date',
        'status',
        'teacher_feedback',
        'completed_at',
        'reviewed_at',
        'attachment_links',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
            'attachment_links' => 'array',
        ];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function learningResources(): BelongsToMany
    {
        return $this->belongsToMany(LearningResource::class, 'homework_learning_resource', 'homework_id', 'learning_resource_id')
            ->withPivot(['assigned_by', 'assigned_at'])
            ->withTimestamps();
    }
}
