<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CourseProgram extends Model
{
    use HasFactory;

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

    public function courseType(): BelongsTo
    {
        return $this->belongsTo(CourseType::class);
    }

    public function learningResources(): BelongsToMany
    {
        return $this->belongsToMany(LearningResource::class, 'course_program_learning_resource')
            ->withPivot(['attached_by', 'attached_at'])
            ->withTimestamps();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    /**
     * @param  Builder<CourseProgram>  $query
     * @return Builder<CourseProgram>
     */
    public function scopeWithArchived(Builder $query): Builder
    {
        return $query->withoutGlobalScope('notArchived');
    }

    /**
     * @param  Builder<CourseProgram>  $query
     * @return Builder<CourseProgram>
     */
    public function scopeOnlyArchived(Builder $query): Builder
    {
        return $query->withArchived()->where('is_archived', true);
    }
}
