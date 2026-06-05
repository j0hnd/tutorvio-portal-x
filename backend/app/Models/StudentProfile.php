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

    /**
     * Get the user inverse relationship for this student profile.
     *
     * This user-facing relationship resolves one User model.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the assigned teacher inverse relationship for this student profile.
     *
     * This user-facing relationship resolves one User model.
     */
    public function assignedTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_teacher_id');
    }

    /**
     * Get the teacher assignments one-to-many relationship for this student profile.
     *
     * This user-facing relationship resolves multiple TeacherStudentAssignment models.
     */
    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(TeacherStudentAssignment::class, 'student_id', 'user_id');
    }

    /**
     * Get the active teacher assignment one-to-one relationship for this student profile.
     *
     * This user-facing relationship resolves one TeacherStudentAssignment model.
     * Important query filters: status.
     */
    public function activeTeacherAssignment(): HasOne
    {
        return $this->hasOne(TeacherStudentAssignment::class, 'student_id', 'user_id')
            ->where('status', TeacherStudentAssignment::STATUS_ACTIVE);
    }

    /**
     * Get the lessons one-to-many relationship for this student profile.
     *
     * This user-facing relationship resolves multiple Lesson models.
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class, 'student_id', 'user_id');
    }

    /**
     * Get the lesson records one-to-many relationship for this student profile.
     *
     * This user-facing relationship resolves multiple LessonRecord models.
     */
    public function lessonRecords(): HasMany
    {
        return $this->hasMany(LessonRecord::class, 'student_id', 'user_id');
    }

    /**
     * Get the progress records one-to-many relationship for this student profile.
     *
     * This user-facing relationship resolves multiple StudentProgressRecord models.
     */
    public function progressRecords(): HasMany
    {
        return $this->hasMany(StudentProgressRecord::class, 'student_id', 'user_id');
    }

    /**
     * Get the lesson notes one-to-many relationship for this student profile.
     *
     * This user-facing relationship resolves multiple LessonNote models.
     */
    public function lessonNotes(): HasMany
    {
        return $this->hasMany(LessonNote::class, 'student_id', 'user_id');
    }

    /**
     * Get the attendances one-to-many relationship for this student profile.
     *
     * This user-facing relationship resolves multiple Attendance models.
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'student_id', 'user_id');
    }

    /**
     * Get the materials many-to-many relationship for this student profile.
     *
     * This user-facing relationship resolves multiple Material models.
     * Important query filters: pivot columns assigned_at, completed_at.
     */
    public function materials(): BelongsToMany
    {
        return $this->belongsToMany(Material::class, 'student_materials', 'student_id', 'material_id')
            ->withPivot(['assigned_at', 'completed_at'])
            ->withTimestamps();
    }

    /**
     * Get the learning resources many-to-many relationship for this student profile.
     *
     * This user-facing relationship resolves multiple LearningResource models.
     * Important query filters: pivot columns assigned_by, assigned_at.
     */
    public function learningResources(): BelongsToMany
    {
        return $this->belongsToMany(LearningResource::class, 'learning_resource_student', 'student_id', 'learning_resource_id', 'user_id')
            ->withPivot(['assigned_by', 'assigned_at'])
            ->withTimestamps();
    }

    /**
     * Get the subscriptions one-to-many relationship for this student profile.
     *
     * This user-facing relationship resolves multiple Subscription models.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'user_id', 'user_id');
    }

    /**
     * Get the subscription histories one-to-many relationship for this student profile.
     *
     * This user-facing relationship resolves multiple SubscriptionHistory models.
     */
    public function subscriptionHistories(): HasMany
    {
        return $this->hasMany(SubscriptionHistory::class, 'student_id', 'user_id');
    }

    /**
     * Get the invoices one-to-many relationship for this student profile.
     *
     * This user-facing relationship resolves multiple Invoice models.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'student_id', 'user_id');
    }

    /**
     * Get the conversations one-to-many relationship for this student profile.
     *
     * This user-facing relationship resolves multiple Conversation models.
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'student_id', 'user_id');
    }
}
