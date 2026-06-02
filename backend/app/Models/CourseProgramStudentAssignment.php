<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseProgramStudentAssignment extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REMOVED = 'removed';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_REMOVED,
    ];

    protected $fillable = [
        'course_program_id',
        'student_id',
        'assigned_by',
        'assigned_at',
        'status',
        'start_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'immutable_datetime',
            'start_date' => 'date',
        ];
    }

    /**
     * Get the course program inverse relationship for this course program student assignment.
     *
     * This user-facing relationship resolves one CourseProgram model.
     */
    public function courseProgram(): BelongsTo
    {
        return $this->belongsTo(CourseProgram::class);
    }

    /**
     * Get the student inverse relationship for this course program student assignment.
     *
     * This user-facing relationship resolves one User model.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Get the assigned by inverse relationship for this course program student assignment.
     *
     * This admin/internal relationship resolves one User model.
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Scope the query to active records.
     *
     * Important query filters: status.
     *
     * @param  Builder<CourseProgramStudentAssignment>  $query
     * @return Builder<CourseProgramStudentAssignment>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
