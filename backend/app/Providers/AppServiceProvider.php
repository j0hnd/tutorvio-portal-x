<?php

namespace App\Providers;

use App\Models\AcademicRecord;
use App\Models\AuditLog;
use App\Models\CourseProgram;
use App\Models\CourseType;
use App\Models\Homework;
use App\Models\Invoice;
use App\Models\LearningResource;
use App\Models\LessonNote;
use App\Models\LessonRecord;
use App\Models\PayoutPeriod;
use App\Models\ScheduleChangeRequest;
use App\Models\Scheduling\ClassSchedule;
use App\Models\Scheduling\Holiday;
use App\Models\Scheduling\ScheduleReminder;
use App\Models\Scheduling\TeacherAvailability;
use App\Models\Scheduling\TeacherUnavailableDate;
use App\Models\StudentProgressRecord;
use App\Models\Subscription;
use App\Models\TeacherChangeRequest;
use App\Models\TeacherCompensation;
use App\Models\TeacherEarning;
use App\Models\TeacherStudentAssignment;
use App\Models\User;
use App\Policies\AcademicRecordPolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\ClassSchedulePolicy;
use App\Policies\CourseProgramPolicy;
use App\Policies\CourseTypePolicy;
use App\Policies\HolidayPolicy;
use App\Policies\HomeworkPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\LearningResourcePolicy;
use App\Policies\LessonNotePolicy;
use App\Policies\LessonRecordPolicy;
use App\Policies\PayoutPeriodPolicy;
use App\Policies\ScheduleChangeRequestPolicy;
use App\Policies\ScheduleReminderPolicy;
use App\Policies\StudentProgressRecordPolicy;
use App\Policies\SubscriptionPolicy;
use App\Policies\TeacherAvailabilityPolicy;
use App\Policies\TeacherChangeRequestPolicy;
use App\Policies\TeacherCompensationPolicy;
use App\Policies\TeacherEarningPolicy;
use App\Policies\TeacherStudentAssignmentPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(ClassSchedule::class, ClassSchedulePolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(AcademicRecord::class, AcademicRecordPolicy::class);
        Gate::policy(TeacherAvailability::class, TeacherAvailabilityPolicy::class);
        Gate::policy(TeacherUnavailableDate::class, TeacherAvailabilityPolicy::class);
        Gate::policy(Holiday::class, HolidayPolicy::class);
        Gate::policy(ScheduleReminder::class, ScheduleReminderPolicy::class);
        Gate::policy(ScheduleChangeRequest::class, ScheduleChangeRequestPolicy::class);
        Gate::policy(LessonNote::class, LessonNotePolicy::class);
        Gate::policy(LessonRecord::class, LessonRecordPolicy::class);
        Gate::policy(StudentProgressRecord::class, StudentProgressRecordPolicy::class);
        Gate::policy(LearningResource::class, LearningResourcePolicy::class);
        Gate::policy(Homework::class, HomeworkPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(Subscription::class, SubscriptionPolicy::class);
        Gate::policy(CourseType::class, CourseTypePolicy::class);
        Gate::policy(CourseProgram::class, CourseProgramPolicy::class);
        Gate::policy(TeacherCompensation::class, TeacherCompensationPolicy::class);
        Gate::policy(TeacherEarning::class, TeacherEarningPolicy::class);
        Gate::policy(TeacherStudentAssignment::class, TeacherStudentAssignmentPolicy::class);
        Gate::policy(TeacherChangeRequest::class, TeacherChangeRequestPolicy::class);
        Gate::policy(PayoutPeriod::class, PayoutPeriodPolicy::class);

        Gate::define('viewTeacherWorkloads', function (User $user): bool {
            return $user->hasRole('admin')
                || ($user->hasRole('staff') && ($user->can('teacher_workloads.view') || $user->can('school_reports.view')))
                || $user->hasRole('teacher');
        });

        Gate::define('viewTeacherWorkload', function (User $user, User $teacher): bool {
            return $user->hasRole('admin')
                || ($user->hasRole('staff') && $user->can('teacher_workloads.view'))
                || ($user->hasRole('teacher') && (int) $user->id === (int) $teacher->id);
        });
    }
}
