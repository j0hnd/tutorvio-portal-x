<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseType extends Model
{
    use HasFactory, HasPublicId;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'sort_order',
        'is_archived',
        'archived_at',
        'archived_by',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
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
     * Get the programs one-to-many relationship for this course type.
     *
     * This user-facing relationship resolves multiple CourseProgram models.
     */
    public function programs(): HasMany
    {
        return $this->hasMany(CourseProgram::class);
    }

    /**
     * Get the teacher compensation rate rules one-to-many relationship for this course type.
     *
     * This admin/internal relationship resolves multiple TeacherCompensationRateRule models.
     */
    public function teacherCompensationRateRules(): HasMany
    {
        return $this->hasMany(TeacherCompensationRateRule::class);
    }

    /**
     * Get the created by inverse relationship for this course type.
     *
     * This admin/internal relationship resolves one User model.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the updated by inverse relationship for this course type.
     *
     * This admin/internal relationship resolves one User model.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the archived by inverse relationship for this course type.
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
     * @param  Builder<CourseType>  $query
     * @return Builder<CourseType>
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
     * @param  Builder<CourseType>  $query
     * @return Builder<CourseType>
     */
    public function scopeOnlyArchived(Builder $query): Builder
    {
        return $query->withArchived()->where('is_archived', true);
    }
}
