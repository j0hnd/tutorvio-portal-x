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

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'student_materials', 'material_id', 'student_id')
            ->withPivot(['assigned_at', 'completed_at'])
            ->withTimestamps();
    }

    public function lessonRecords(): BelongsToMany
    {
        return $this->belongsToMany(LessonRecord::class, 'lesson_record_materials')
            ->withTimestamps();
    }
}
