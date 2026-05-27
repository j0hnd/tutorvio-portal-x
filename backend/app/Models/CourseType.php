<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseType extends Model
{
    use HasFactory;

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

    public function programs(): HasMany
    {
        return $this->hasMany(CourseProgram::class);
    }

    public function teacherCompensationRateRules(): HasMany
    {
        return $this->hasMany(TeacherCompensationRateRule::class);
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
     * @param  Builder<CourseType>  $query
     * @return Builder<CourseType>
     */
    public function scopeWithArchived(Builder $query): Builder
    {
        return $query->withoutGlobalScope('notArchived');
    }

    /**
     * @param  Builder<CourseType>  $query
     * @return Builder<CourseType>
     */
    public function scopeOnlyArchived(Builder $query): Builder
    {
        return $query->withArchived()->where('is_archived', true);
    }
}
