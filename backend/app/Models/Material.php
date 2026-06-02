<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Material extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'url',
    ];

    /**
     * Get the students many-to-many relationship for this material.
     *
     * This user-facing relationship resolves multiple User models.
     * Important query filters: pivot columns assigned_at, completed_at.
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'student_materials', 'material_id', 'student_id')
            ->withPivot(['assigned_at', 'completed_at'])
            ->withTimestamps();
    }

    /**
     * Get the lesson records many-to-many relationship for this material.
     *
     * This user-facing relationship resolves multiple LessonRecord models.
     */
    public function lessonRecords(): BelongsToMany
    {
        return $this->belongsToMany(LessonRecord::class, 'lesson_record_materials')
            ->withTimestamps();
    }
}
