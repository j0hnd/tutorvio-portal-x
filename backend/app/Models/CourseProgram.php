<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseProgram extends Model
{
    use HasFactory, HasPublicId;

    protected $fillable = [
        'course_type_id',
        'title',
        'slug',
        'description',
        'placement_level',
        'number_of_sessions',
        'lesson_structure',
        'milestones',
        'is_archived',
        'archived_at',
        'archived_by',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'number_of_sessions' => 'integer',
            'lesson_structure' => 'array',
            'milestones' => 'array',
            'is_archived' => 'boolean',
            'archived_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('notArchived', function (Builder $query) {
            $query->where('is_archived', false);
        });
    }

    /**
     * Get the course type inverse relationship for this course program.
     *
     * This user-facing relationship resolves one CourseType model.
     */
    public function courseType(): BelongsTo
    {
        return $this->belongsTo(CourseType::class);
    }

    /**
     * Get the learning resources many-to-many relationship for this course program.
     *
     * This user-facing relationship resolves multiple LearningResource models.
     * Important query filters: pivot columns attached_by, attached_at.
     */
    public function learningResources(): BelongsToMany
    {
        return $this->belongsToMany(LearningResource::class, 'course_program_learning_resource')
            ->withPivot(['attached_by', 'attached_at'])
            ->withTimestamps();
    }

    /**
     * Get the student assignments one-to-many relationship for this course program.
     *
     * This user-facing relationship resolves multiple CourseProgramStudentAssignment models.
     */
    public function studentAssignments(): HasMany
    {
        return $this->hasMany(CourseProgramStudentAssignment::class);
    }

    /**
     * Get the invoices one-to-many relationship for this course program.
     *
     * This user-facing relationship resolves multiple Invoice models.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get the teacher compensation rate rules one-to-many relationship for this course program.
     *
     * This admin/internal relationship resolves multiple TeacherCompensationRateRule models.
     */
    public function teacherCompensationRateRules(): HasMany
    {
        return $this->hasMany(TeacherCompensationRateRule::class);
    }

    /**
     * Get the created by inverse relationship for this course program.
     *
     * This admin/internal relationship resolves one User model.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the updated by inverse relationship for this course program.
     *
     * This admin/internal relationship resolves one User model.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the archived by inverse relationship for this course program.
     *
     * This admin/internal relationship resolves one User model.
     */
    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    /**
     * Scope the query to with archived records.
     *
     * Important query filters: notArchived global scope.
     *
     * @param  Builder<CourseProgram>  $query
     * @return Builder<CourseProgram>
     */
    public function scopeWithArchived(Builder $query): Builder
    {
        return $query->withoutGlobalScope('notArchived');
    }

    /**
     * Scope the query to only archived records.
     *
     * Important query filters: is_archived, withArchived scope.
     *
     * @param  Builder<CourseProgram>  $query
     * @return Builder<CourseProgram>
     */
    public function scopeOnlyArchived(Builder $query): Builder
    {
        return $query->withArchived()->where('is_archived', true);
    }

    /**
     * Scope the query to visible to records.
     *
     * Important query filters: assigned_teacher_id, student_id, studentAssignments relation, student.studentProfile relation.
     *
     * @param  Builder<CourseProgram>  $query
     * @return Builder<CourseProgram>
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user?->hasRole('admin')) {
            return $query;
        }

        if ($user?->hasRole('staff') && $user->can('course_programs.view')) {
            return $query;
        }

        if ($user?->hasRole('teacher')) {
            return $query->whereHas('studentAssignments', function (Builder $query) use ($user) {
                $query
                    ->active()
                    ->whereHas('student.studentProfile', fn (Builder $query) => $query->where('assigned_teacher_id', $user->id));
            });
        }

        if ($user?->hasRole('student')) {
            return $query->whereHas('studentAssignments', function (Builder $query) use ($user) {
                $query
                    ->active()
                    ->where('student_id', $user->id);
            });
        }

        return $query->whereRaw('1 = 0');
    }
}
