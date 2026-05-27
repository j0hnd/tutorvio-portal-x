<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StudentProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'english_level',
        'current_level',
        'course',
        'assigned_teacher_id',
        'class_type',
        'start_date',
        'notes',
        'teacher_notes',
        'internal_notes',
        'preferences',
        'goals',
        'learning_concerns',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_teacher_id');
    }

    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(TeacherStudentAssignment::class, 'student_id', 'user_id');
    }

    public function activeTeacherAssignment(): HasOne
    {
        return $this->hasOne(TeacherStudentAssignment::class, 'student_id', 'user_id')
            ->where('status', TeacherStudentAssignment::STATUS_ACTIVE);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class, 'student_id', 'user_id');
    }

    public function lessonRecords(): HasMany
    {
        return $this->hasMany(LessonRecord::class, 'student_id', 'user_id');
    }

    public function progressRecords(): HasMany
    {
        return $this->hasMany(StudentProgressRecord::class, 'student_id', 'user_id');
    }

    public function lessonNotes(): HasMany
    {
        return $this->hasMany(LessonNote::class, 'student_id', 'user_id');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'student_id', 'user_id');
    }

    public function materials()
    {
        return $this->belongsToMany(Material::class, 'student_materials', 'student_id', 'material_id')
            ->withPivot(['assigned_at', 'completed_at'])
            ->withTimestamps();
    }

    public function learningResources(): BelongsToMany
    {
        return $this->belongsToMany(LearningResource::class, 'learning_resource_student', 'student_id', 'learning_resource_id', 'user_id')
            ->withPivot(['assigned_by', 'assigned_at'])
            ->withTimestamps();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'user_id', 'user_id');
    }

    public function subscriptionHistories(): HasMany
    {
        return $this->hasMany(SubscriptionHistory::class, 'student_id', 'user_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'student_id', 'user_id');
    }
}
