<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Scheduling\ClassSchedule;
use App\Models\Scheduling\ScheduleReminder;
use App\Models\Scheduling\TeacherAvailability;
use App\Models\Scheduling\TeacherUnavailableDate;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'phone', 'timezone', 'profile_photo_path', 'signed_document_path', 'password', 'status', 'email_verified_at', 'invited_at', 'activated_at', 'created_by', 'updated_by'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_INVITED = 'invited';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_INVITED,
        self::STATUS_SUSPENDED,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'invited_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }

    public function studentProfile(): HasOne
    {
        return $this->hasOne(StudentProfile::class);
    }

    public function teacherProfile(): HasOne
    {
        return $this->hasOne(TeacherProfile::class);
    }

    public function staffProfile(): HasOne
    {
        return $this->hasOne(StaffProfile::class);
    }

    public function assignedStudents(): HasMany
    {
        return $this->hasMany(StudentProfile::class, 'assigned_teacher_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(UserStatusHistory::class);
    }

    public function createdUsers(): HasMany
    {
        return $this->hasMany(self::class, 'created_by');
    }

    public function updatedUsers(): HasMany
    {
        return $this->hasMany(self::class, 'updated_by');
    }

    public function studentClassSchedules(): HasMany
    {
        return $this->hasMany(ClassSchedule::class, 'student_id');
    }

    public function teacherClassSchedules(): HasMany
    {
        return $this->hasMany(ClassSchedule::class, 'teacher_id');
    }

    public function studentLessonRecords(): HasMany
    {
        return $this->hasMany(LessonRecord::class, 'student_id');
    }

    public function teacherLessonRecords(): HasMany
    {
        return $this->hasMany(LessonRecord::class, 'teacher_id');
    }

    public function createdLessonRecords(): HasMany
    {
        return $this->hasMany(LessonRecord::class, 'created_by');
    }

    public function updatedLessonRecords(): HasMany
    {
        return $this->hasMany(LessonRecord::class, 'updated_by');
    }

    public function completedLessonRecords(): HasMany
    {
        return $this->hasMany(LessonRecord::class, 'completed_by');
    }

    public function teacherAvailabilities(): HasMany
    {
        return $this->hasMany(TeacherAvailability::class, 'teacher_id');
    }

    public function teacherUnavailableDates(): HasMany
    {
        return $this->hasMany(TeacherUnavailableDate::class, 'teacher_id');
    }

    public function scheduleReminders(): HasMany
    {
        return $this->hasMany(ScheduleReminder::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'updated_by');
    }
}
