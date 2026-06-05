<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeacherProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'specialization',
        'bio',
        'expertise',
        'class_load',
        'teaching_availability',
        'performance_summary',
        'internal_status',
        'teaching_notes',
        'internal_remarks',
        'document_contract_status',
    ];

    protected function casts(): array
    {
        return [
            'teaching_availability' => 'array',
        ];
    }

    /**
     * Get the user inverse relationship for this teacher profile.
     *
     * This user-facing relationship resolves one User model.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the assigned students one-to-many relationship for this teacher profile.
     *
     * This user-facing relationship resolves multiple StudentProfile models.
     */
    public function assignedStudents(): HasMany
    {
        return $this->hasMany(StudentProfile::class, 'assigned_teacher_id', 'user_id');
    }

    /**
     * Get the lessons one-to-many relationship for this teacher profile.
     *
     * This user-facing relationship resolves multiple Lesson models.
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class, 'teacher_id', 'user_id');
    }

    /**
     * Get the lesson records one-to-many relationship for this teacher profile.
     *
     * This user-facing relationship resolves multiple LessonRecord models.
     */
    public function lessonRecords(): HasMany
    {
        return $this->hasMany(LessonRecord::class, 'teacher_id', 'user_id');
    }

    /**
     * Get the lesson notes one-to-many relationship for this teacher profile.
     *
     * This user-facing relationship resolves multiple LessonNote models.
     */
    public function lessonNotes(): HasMany
    {
        return $this->hasMany(LessonNote::class, 'teacher_id', 'user_id');
    }

    /**
     * Get the compensations one-to-many relationship for this teacher profile.
     *
     * This admin/internal relationship resolves multiple TeacherCompensation models.
     */
    public function compensations(): HasMany
    {
        return $this->hasMany(TeacherCompensation::class, 'teacher_id', 'user_id');
    }
}
