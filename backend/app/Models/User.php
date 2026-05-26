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
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    public function lessonJoinAccessLogs(): HasMany
    {
        return $this->hasMany(LessonJoinAccessLog::class);
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

    public function studentLessonNotes(): HasMany
    {
        return $this->hasMany(LessonNote::class, 'student_id');
    }

    public function studentHomeworks(): HasMany
    {
        return $this->hasMany(Homework::class, 'student_id');
    }

    public function studentProgressRecords(): HasMany
    {
        return $this->hasMany(StudentProgressRecord::class, 'student_id');
    }

    public function assignedLearningResources(): BelongsToMany
    {
        return $this->belongsToMany(LearningResource::class, 'learning_resource_student', 'student_id', 'learning_resource_id')
            ->withPivot(['assigned_by', 'assigned_at'])
            ->withTimestamps();
    }

    public function courseProgramAssignments(): HasMany
    {
        return $this->hasMany(CourseProgramStudentAssignment::class, 'student_id');
    }

    public function createdCourseTypes(): HasMany
    {
        return $this->hasMany(CourseType::class, 'created_by');
    }

    public function updatedCourseTypes(): HasMany
    {
        return $this->hasMany(CourseType::class, 'updated_by');
    }

    public function archivedCourseTypes(): HasMany
    {
        return $this->hasMany(CourseType::class, 'archived_by');
    }

    public function createdCoursePrograms(): HasMany
    {
        return $this->hasMany(CourseProgram::class, 'created_by');
    }

    public function updatedCoursePrograms(): HasMany
    {
        return $this->hasMany(CourseProgram::class, 'updated_by');
    }

    public function archivedCoursePrograms(): HasMany
    {
        return $this->hasMany(CourseProgram::class, 'archived_by');
    }

    public function teacherLessonNotes(): HasMany
    {
        return $this->hasMany(LessonNote::class, 'teacher_id');
    }

    public function teacherHomeworks(): HasMany
    {
        return $this->hasMany(Homework::class, 'teacher_id');
    }

    public function teacherProgressRecords(): HasMany
    {
        return $this->hasMany(StudentProgressRecord::class, 'teacher_id');
    }

    public function authoredLessonNotes(): HasMany
    {
        return $this->hasMany(LessonNote::class, 'author_id');
    }

    public function createdLessonRecords(): HasMany
    {
        return $this->hasMany(LessonRecord::class, 'created_by');
    }

    public function updatedLessonRecords(): HasMany
    {
        return $this->hasMany(LessonRecord::class, 'updated_by');
    }

    public function createdStudentProgressRecords(): HasMany
    {
        return $this->hasMany(StudentProgressRecord::class, 'created_by');
    }

    public function updatedStudentProgressRecords(): HasMany
    {
        return $this->hasMany(StudentProgressRecord::class, 'updated_by');
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

    public function sentNotifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'sender_id');
    }

    public function notificationRecipients(): HasMany
    {
        return $this->hasMany(NotificationRecipient::class);
    }

    public function authoredAnnouncements(): HasMany
    {
        return $this->hasMany(Announcement::class, 'author_id');
    }

    public function announcementReadStates(): HasMany
    {
        return $this->hasMany(AnnouncementReadState::class);
    }

    public function studentMessageThreads(): HasMany
    {
        return $this->hasMany(MessageThread::class, 'student_id');
    }

    public function teacherMessageThreads(): HasMany
    {
        return $this->hasMany(MessageThread::class, 'teacher_id');
    }

    public function createdMessageThreads(): HasMany
    {
        return $this->hasMany(MessageThread::class, 'created_by');
    }

    public function messageThreadParticipants(): HasMany
    {
        return $this->hasMany(MessageThreadParticipant::class);
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
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
