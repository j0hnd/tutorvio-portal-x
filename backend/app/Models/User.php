<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\AppliesFullTextSearch;
use App\Models\Concerns\HasPublicId;
use App\Models\Scheduling\ClassSchedule;
use App\Models\Scheduling\ScheduleReminder;
use App\Models\Scheduling\TeacherAvailability;
use App\Models\Scheduling\TeacherUnavailableDate;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
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
    use AppliesFullTextSearch, HasApiTokens, HasFactory, HasPublicId, HasRoles, Notifiable;

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
     * Scope the query to searchable user identity fields.
     */
    public function scopeSearchIdentity(Builder $query, ?string $term): Builder
    {
        return $this->applyFullTextSearch($query, ['name', 'email'], $term);
    }

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

    /**
     * Get the student profile one-to-one relationship for this user.
     *
     * This user-facing relationship resolves one StudentProfile model.
     */
    public function studentProfile(): HasOne
    {
        return $this->hasOne(StudentProfile::class);
    }

    /**
     * Get the teacher profile one-to-one relationship for this user.
     *
     * This user-facing relationship resolves one TeacherProfile model.
     */
    public function teacherProfile(): HasOne
    {
        return $this->hasOne(TeacherProfile::class);
    }

    /**
     * Get the staff profile one-to-one relationship for this user.
     *
     * This admin/internal relationship resolves one StaffProfile model.
     */
    public function staffProfile(): HasOne
    {
        return $this->hasOne(StaffProfile::class);
    }

    /**
     * Get the assigned students one-to-many relationship for this user.
     *
     * This user-facing relationship resolves multiple StudentProfile models.
     */
    public function assignedStudents(): HasMany
    {
        return $this->hasMany(StudentProfile::class, 'assigned_teacher_id');
    }

    /**
     * Get the student teacher assignments one-to-many relationship for this user.
     *
     * This user-facing relationship resolves multiple TeacherStudentAssignment models.
     */
    public function studentTeacherAssignments(): HasMany
    {
        return $this->hasMany(TeacherStudentAssignment::class, 'student_id');
    }

    /**
     * Get the teacher change requests one-to-many relationship for this user.
     *
     * This user-facing relationship resolves multiple TeacherChangeRequest models.
     */
    public function teacherChangeRequests(): HasMany
    {
        return $this->hasMany(TeacherChangeRequest::class, 'student_id');
    }

    /**
     * Get the reviewed teacher change requests one-to-many relationship for this user.
     *
     * This admin/internal relationship resolves multiple TeacherChangeRequest models.
     */
    public function reviewedTeacherChangeRequests(): HasMany
    {
        return $this->hasMany(TeacherChangeRequest::class, 'reviewed_by');
    }

    /**
     * Get the active teacher assignment one-to-one relationship for this user.
     *
     * This user-facing relationship resolves one TeacherStudentAssignment model.
     * Important query filters: status.
     */
    public function activeTeacherAssignment(): HasOne
    {
        return $this->hasOne(TeacherStudentAssignment::class, 'student_id')
            ->where('status', TeacherStudentAssignment::STATUS_ACTIVE);
    }

    /**
     * Get the teacher student assignments one-to-many relationship for this user.
     *
     * This user-facing relationship resolves multiple TeacherStudentAssignment models.
     */
    public function teacherStudentAssignments(): HasMany
    {
        return $this->hasMany(TeacherStudentAssignment::class, 'teacher_id');
    }

    /**
     * Get the created teacher student assignments one-to-many relationship for this user.
     *
     * This admin/internal relationship resolves multiple TeacherStudentAssignment models.
     */
    public function createdTeacherStudentAssignments(): HasMany
    {
        return $this->hasMany(TeacherStudentAssignment::class, 'assigned_by');
    }

    /**
     * Get the status histories one-to-many relationship for this user.
     *
     * This admin/internal relationship resolves multiple UserStatusHistory models.
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(UserStatusHistory::class);
    }

    /**
     * Get the lesson join access logs one-to-many relationship for this user.
     *
     * This user-facing relationship resolves multiple LessonJoinAccessLog models.
     */
    public function lessonJoinAccessLogs(): HasMany
    {
        return $this->hasMany(LessonJoinAccessLog::class);
    }

    /**
     * Get the created users one-to-many relationship for this user.
     *
     * This admin/internal relationship resolves multiple User models.
     */
    public function createdUsers(): HasMany
    {
        return $this->hasMany(self::class, 'created_by');
    }

    /**
     * Get the updated users one-to-many relationship for this user.
     *
     * This admin/internal relationship resolves multiple User models.
     */
    public function updatedUsers(): HasMany
    {
        return $this->hasMany(self::class, 'updated_by');
    }

    /**
     * Get the student class schedules one-to-many relationship for this user.
     *
     * This user-facing relationship resolves multiple ClassSchedule models.
     */
    public function studentClassSchedules(): HasMany
    {
        return $this->hasMany(ClassSchedule::class, 'student_id');
    }

    /**
     * Get the teacher class schedules one-to-many relationship for this user.
     *
     * This user-facing relationship resolves multiple ClassSchedule models.
     */
    public function teacherClassSchedules(): HasMany
    {
        return $this->hasMany(ClassSchedule::class, 'teacher_id');
    }

    /**
     * Get the student lesson records one-to-many relationship for this user.
     *
     * This user-facing relationship resolves multiple LessonRecord models.
     */
    public function studentLessonRecords(): HasMany
    {
        return $this->hasMany(LessonRecord::class, 'student_id');
    }

    /**
     * Get the teacher lesson records one-to-many relationship for this user.
     *
     * This user-facing relationship resolves multiple LessonRecord models.
     */
    public function teacherLessonRecords(): HasMany
    {
        return $this->hasMany(LessonRecord::class, 'teacher_id');
    }

    /**
     * Get the student lesson notes one-to-many relationship for this user.
     *
     * This user-facing relationship resolves multiple LessonNote models.
     */
    public function studentLessonNotes(): HasMany
    {
        return $this->hasMany(LessonNote::class, 'student_id');
    }

    /**
     * Get the student homeworks one-to-many relationship for this user.
     *
     * This user-facing relationship resolves multiple Homework models.
     */
    public function studentHomeworks(): HasMany
    {
        return $this->hasMany(Homework::class, 'student_id');
    }

    /**
     * Get the student progress records one-to-many relationship for this user.
     *
     * This user-facing relationship resolves multiple StudentProgressRecord models.
     */
    public function studentProgressRecords(): HasMany
    {
        return $this->hasMany(StudentProgressRecord::class, 'student_id');
    }

    /**
     * Get the student invoices one-to-many relationship for this user.
     *
     * This user-facing relationship resolves multiple Invoice models.
     */
    public function studentInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'student_id');
    }

    /**
     * Get the subscriptions one-to-many relationship for this user.
     *
     * This user-facing relationship resolves multiple Subscription models.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'user_id');
    }

    /**
     * Get the subscription histories one-to-many relationship for this user.
     *
     * This user-facing relationship resolves multiple SubscriptionHistory models.
     */
    public function subscriptionHistories(): HasMany
    {
        return $this->hasMany(SubscriptionHistory::class, 'student_id');
    }

    /**
     * Get the assigned learning resources many-to-many relationship for this user.
     *
     * This user-facing relationship resolves multiple LearningResource models.
     * Important query filters: pivot columns assigned_by, assigned_at.
     */
    public function assignedLearningResources(): BelongsToMany
    {
        return $this->belongsToMany(LearningResource::class, 'learning_resource_student', 'student_id', 'learning_resource_id')
            ->withPivot(['assigned_by', 'assigned_at'])
            ->withTimestamps();
    }

    /**
     * Get the course program assignments one-to-many relationship for this user.
     *
     * This user-facing relationship resolves multiple CourseProgramStudentAssignment models.
     */
    public function courseProgramAssignments(): HasMany
    {
        return $this->hasMany(CourseProgramStudentAssignment::class, 'student_id');
    }

    /**
     * Get the created course types one-to-many relationship for this user.
     *
     * This admin/internal relationship resolves multiple CourseType models.
     */
    public function createdCourseTypes(): HasMany
    {
        return $this->hasMany(CourseType::class, 'created_by');
    }

    /**
     * Get the updated course types one-to-many relationship for this user.
     *
     * This admin/internal relationship resolves multiple CourseType models.
     */
    public function updatedCourseTypes(): HasMany
    {
        return $this->hasMany(CourseType::class, 'updated_by');
    }

    /**
     * Get the archived course types one-to-many relationship for this user.
     *
     * This admin/internal relationship resolves multiple CourseType models.
     */
    public function archivedCourseTypes(): HasMany
    {
        return $this->hasMany(CourseType::class, 'archived_by');
    }

    /**
     * Get the created course programs one-to-many relationship for this user.
     *
     * This admin/internal relationship resolves multiple CourseProgram models.
     */
    public function createdCoursePrograms(): HasMany
    {
        return $this->hasMany(CourseProgram::class, 'created_by');
    }

    /**
     * Get the updated course programs one-to-many relationship for this user.
     *
     * This admin/internal relationship resolves multiple CourseProgram models.
     */
    public function updatedCoursePrograms(): HasMany
    {
        return $this->hasMany(CourseProgram::class, 'updated_by');
    }

    /**
     * Get the archived course programs one-to-many relationship for this user.
     *
     * This admin/internal relationship resolves multiple CourseProgram models.
     */
    public function archivedCoursePrograms(): HasMany
    {
        return $this->hasMany(CourseProgram::class, 'archived_by');
    }

    /**
     * Get the teacher lesson notes one-to-many relationship for this user.
     *
     * This user-facing relationship resolves multiple LessonNote models.
     */
    public function teacherLessonNotes(): HasMany
    {
        return $this->hasMany(LessonNote::class, 'teacher_id');
    }

    /**
     * Get the teacher homeworks one-to-many relationship for this user.
     *
     * This user-facing relationship resolves multiple Homework models.
     */
    public function teacherHomeworks(): HasMany
    {
        return $this->hasMany(Homework::class, 'teacher_id');
    }

    /**
     * Get the teacher progress records one-to-many relationship for this user.
     *
     * This user-facing relationship resolves multiple StudentProgressRecord models.
     */
    public function teacherProgressRecords(): HasMany
    {
        return $this->hasMany(StudentProgressRecord::class, 'teacher_id');
    }

    /**
     * Get the teacher compensations one-to-many relationship for this user.
     *
     * This admin/internal relationship resolves multiple TeacherCompensation models.
     */
    public function teacherCompensations(): HasMany
    {
        return $this->hasMany(TeacherCompensation::class, 'teacher_id');
    }

    /**
     * Get the teacher earnings one-to-many relationship for this user.
     *
     * This admin/internal relationship resolves multiple TeacherEarning models.
     */
    public function teacherEarnings(): HasMany
    {
        return $this->hasMany(TeacherEarning::class, 'teacher_id');
    }

    /**
     * Get the authored lesson notes one-to-many relationship for this user.
     *
     * This user-facing relationship resolves multiple LessonNote models.
     */
    public function authoredLessonNotes(): HasMany
    {
        return $this->hasMany(LessonNote::class, 'author_id');
    }

    /**
     * Get the created lesson records one-to-many relationship for this user.
     *
     * This admin/internal relationship resolves multiple LessonRecord models.
     */
    public function createdLessonRecords(): HasMany
    {
        return $this->hasMany(LessonRecord::class, 'created_by');
    }

    /**
     * Get the updated lesson records one-to-many relationship for this user.
     *
     * This admin/internal relationship resolves multiple LessonRecord models.
     */
    public function updatedLessonRecords(): HasMany
    {
        return $this->hasMany(LessonRecord::class, 'updated_by');
    }

    /**
     * Get the created student progress records one-to-many relationship for this user.
     *
     * This admin/internal relationship resolves multiple StudentProgressRecord models.
     */
    public function createdStudentProgressRecords(): HasMany
    {
        return $this->hasMany(StudentProgressRecord::class, 'created_by');
    }

    /**
     * Get the updated student progress records one-to-many relationship for this user.
     *
     * This admin/internal relationship resolves multiple StudentProgressRecord models.
     */
    public function updatedStudentProgressRecords(): HasMany
    {
        return $this->hasMany(StudentProgressRecord::class, 'updated_by');
    }

    /**
     * Get the completed lesson records one-to-many relationship for this user.
     *
     * This admin/internal relationship resolves multiple LessonRecord models.
     */
    public function completedLessonRecords(): HasMany
    {
        return $this->hasMany(LessonRecord::class, 'completed_by');
    }

    /**
     * Get the teacher availabilities one-to-many relationship for this user.
     *
     * This user-facing relationship resolves multiple TeacherAvailability models.
     */
    public function teacherAvailabilities(): HasMany
    {
        return $this->hasMany(TeacherAvailability::class, 'teacher_id');
    }

    /**
     * Get the teacher unavailable dates one-to-many relationship for this user.
     *
     * This user-facing relationship resolves multiple TeacherUnavailableDate models.
     */
    public function teacherUnavailableDates(): HasMany
    {
        return $this->hasMany(TeacherUnavailableDate::class, 'teacher_id');
    }

    /**
     * Get the schedule reminders one-to-many relationship for this user.
     *
     * This user-facing relationship resolves multiple ScheduleReminder models.
     */
    public function scheduleReminders(): HasMany
    {
        return $this->hasMany(ScheduleReminder::class);
    }

    /**
     * Get the sent notifications one-to-many relationship for this user.
     *
     * This user-facing relationship resolves multiple Notification models.
     */
    public function sentNotifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'sender_id');
    }

    /**
     * Get the notification recipients one-to-many relationship for this user.
     *
     * This user-facing relationship resolves multiple NotificationRecipient models.
     */
    public function notificationRecipients(): HasMany
    {
        return $this->hasMany(NotificationRecipient::class);
    }

    /**
     * Get the authored announcements one-to-many relationship for this user.
     *
     * This user-facing relationship resolves multiple Announcement models.
     */
    public function authoredAnnouncements(): HasMany
    {
        return $this->hasMany(Announcement::class, 'author_id');
    }

    /**
     * Get the announcement read states one-to-many relationship for this user.
     *
     * This user-facing relationship resolves multiple AnnouncementReadState models.
     */
    public function announcementReadStates(): HasMany
    {
        return $this->hasMany(AnnouncementReadState::class);
    }

    /**
     * Get the announcement recipients one-to-many relationship for this user.
     *
     * This user-facing relationship resolves multiple AnnouncementRecipient models.
     */
    public function announcementRecipients(): HasMany
    {
        return $this->hasMany(AnnouncementRecipient::class);
    }

    /**
     * Get the student message threads one-to-many relationship for this user.
     *
     * This user-facing relationship resolves multiple MessageThread models.
     */
    public function studentMessageThreads(): HasMany
    {
        return $this->hasMany(MessageThread::class, 'student_id');
    }

    /**
     * Get the teacher message threads one-to-many relationship for this user.
     *
     * This user-facing relationship resolves multiple MessageThread models.
     */
    public function teacherMessageThreads(): HasMany
    {
        return $this->hasMany(MessageThread::class, 'teacher_id');
    }

    /**
     * Get the created message threads one-to-many relationship for this user.
     *
     * This admin/internal relationship resolves multiple MessageThread models.
     */
    public function createdMessageThreads(): HasMany
    {
        return $this->hasMany(MessageThread::class, 'created_by');
    }

    /**
     * Get the message thread participants one-to-many relationship for this user.
     *
     * This user-facing relationship resolves multiple MessageThreadParticipant models.
     */
    public function messageThreadParticipants(): HasMany
    {
        return $this->hasMany(MessageThreadParticipant::class);
    }

    /**
     * Get the sent messages one-to-many relationship for this user.
     *
     * This user-facing relationship resolves multiple Message models.
     */
    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    /**
     * Get the student conversations one-to-many relationship for this user.
     *
     * This user-facing relationship resolves multiple Conversation models.
     */
    public function studentConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'student_id');
    }

    /**
     * Get the teacher conversations one-to-many relationship for this user.
     *
     * This user-facing relationship resolves multiple Conversation models.
     */
    public function teacherConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'teacher_id');
    }

    /**
     * Get the created conversations one-to-many relationship for this user.
     *
     * This admin/internal relationship resolves multiple Conversation models.
     */
    public function createdConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'created_by');
    }

    /**
     * Get the last-message conversations one-to-many relationship for this user.
     *
     * This user-facing relationship resolves multiple Conversation models.
     */
    public function lastMessageConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'last_message_by');
    }

    /**
     * Get the conversation participants one-to-many relationship for this user.
     *
     * This user-facing relationship resolves multiple ConversationParticipant models.
     */
    public function conversationParticipants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    /**
     * Get the conversations many-to-many relationship for this user.
     *
     * This user-facing relationship resolves multiple Conversation models.
     * Important query filters: pivot deleted_at.
     */
    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants')
            ->withPivot([
                'participant_role',
                'participant_role_snapshot',
                'participant_roles_snapshot',
                'joined_at',
                'last_read_at',
                'muted_at',
                'archived_at',
                'metadata',
                'deleted_at',
            ])
            ->wherePivotNull('deleted_at')
            ->withTimestamps();
    }

    /**
     * Get the audit logs one-to-many relationship for this user.
     *
     * This admin/internal relationship resolves multiple AuditLog models.
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_user_id');
    }

    /**
     * Get the created by inverse relationship for this user.
     *
     * This admin/internal relationship resolves one User model.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'created_by');
    }

    /**
     * Get the updated by inverse relationship for this user.
     *
     * This admin/internal relationship resolves one User model.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'updated_by');
    }
}
