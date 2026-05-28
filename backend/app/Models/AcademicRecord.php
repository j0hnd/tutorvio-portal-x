<?php

namespace App\Models;

use App\Models\Scheduling\ClassSchedule;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AcademicRecord extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUS_VOID = 'void';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_ARCHIVED,
        self::STATUS_VOID,
    ];

    protected $fillable = [
        'student_id',
        'teacher_id',
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
    ];

    protected function casts(): array
    {
        return [
            'recorded_on' => 'date',
            'data' => 'array',
            'approved_at' => 'immutable_datetime',
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

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function classSchedule(): BelongsTo
    {
        return $this->belongsTo(ClassSchedule::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
