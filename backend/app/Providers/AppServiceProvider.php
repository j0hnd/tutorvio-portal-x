<?php

namespace App\Providers;

use App\Contracts\Search\SearchService;
use App\Models\AcademicRecord;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\CourseProgram;
use App\Models\CourseType;
use App\Models\Homework;
use App\Models\Invoice;
use App\Models\IssueReport;
use App\Models\LearningResource;
use App\Models\Lesson;
use App\Models\LessonNote;
use App\Models\LessonRecord;
use App\Models\PayoutPeriod;
use App\Models\PortalSetting;
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
use App\Services\DashboardCacheService;
use App\Services\PortalMetadataService;
use App\Services\PortalSettings\PortalSettingsService;
use App\Services\Search\SearchManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SearchService::class, SearchManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('auth-login', function (Request $request): array {
            return [
                Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()),
            ];
        });

        RateLimiter::for('auth-register', function (Request $request): array {
            return [
                Limit::perMinute(3)->by($request->ip()),
            ];
        });

        RateLimiter::for('auth-password-reset', function (Request $request): array {
            return [
                Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()),
            ];
        });

        RateLimiter::for('auth-invite', function (Request $request): array {
            return [
                Limit::perMinute(10)->by(((string) $request->user()?->id).'|'.$request->ip()),
            ];
        });

        RateLimiter::for('auth-invitation-accept', function (Request $request): array {
            return [
                Limit::perMinute(10)->by(((string) $request->route('token')).'|'.$request->ip()),
            ];
        });

        RateLimiter::for('api-public', function (Request $request): array {
            return [
                Limit::perMinute(60)->by($request->ip()),
            ];
        });

        RateLimiter::for('api-action', function (Request $request): array {
            return [
                Limit::perMinute(30)->by($this->rateLimitKey($request)),
            ];
        });

        RateLimiter::for('api-upload', function (Request $request): array {
            return [
                Limit::perMinute(10)->by($this->rateLimitKey($request)),
            ];
        });

        RateLimiter::for('api-download', function (Request $request): array {
            return [
                Limit::perMinute(60)->by($this->rateLimitKey($request)),
            ];
        });

        RateLimiter::for('api-lesson-join', function (Request $request): array {
            return [
                Limit::perMinute(20)->by($this->rateLimitKey($request)),
            ];
        });

        RateLimiter::for('api-report', function (Request $request): array {
            return [
                Limit::perMinute(30)->by($this->rateLimitKey($request)),
            ];
        });

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

        /**
         * Authorize viewing the teacher workload report index.
         *
         * Admins and teachers are allowed. Staff must have either
         * `teacher_workloads.view` or `school_reports.view`. Students and staff
         * without those Spatie permissions are denied.
         */
        Gate::define('viewTeacherWorkloads', function (User $user): bool {
            return $user->hasRole('admin')
                || ($user->hasRole('staff') && ($user->can('teacher_workloads.view') || $user->can('school_reports.view')))
                || $user->hasRole('teacher');
        });

        /**
         * Authorize viewing a single teacher workload.
         *
         * Admins can view any teacher. Staff need `teacher_workloads.view`.
         * Teachers can view only their own workload. Other teachers, students,
         * and staff without permission are denied.
         */
        Gate::define('viewTeacherWorkload', function (User $user, User $teacher): bool {
            return $user->hasRole('admin')
                || ($user->hasRole('staff') && $user->can('teacher_workloads.view'))
                || ($user->hasRole('teacher') && (int) $user->id === (int) $teacher->id);
        });

        PortalSetting::saved(function (PortalSetting $setting): void {
            app(PortalSettingsService::class)->forgetCachedSettings();

            if ($setting->key === 'attendance.status_options') {
                app(PortalMetadataService::class)->forgetAttendanceStatusOptions();
            }
        });
        PortalSetting::deleted(function (PortalSetting $setting): void {
            app(PortalSettingsService::class)->forgetCachedSettings();

            if ($setting->key === 'attendance.status_options') {
                app(PortalMetadataService::class)->forgetAttendanceStatusOptions();
            }
        });

        CourseType::saved(function (): void {
            app(PortalMetadataService::class)->forgetCourseTypes();
        });
        CourseType::deleted(function (): void {
            app(PortalMetadataService::class)->forgetCourseTypes();
        });

        Role::saved(function (): void {
            app(PortalMetadataService::class)->forgetRolePermissionMetadata();
        });
        Role::deleted(function (): void {
            app(PortalMetadataService::class)->forgetRolePermissionMetadata();
        });
        Permission::saved(function (): void {
            app(PortalMetadataService::class)->forgetRolePermissionMetadata();
        });
        Permission::deleted(function (): void {
            app(PortalMetadataService::class)->forgetRolePermissionMetadata();
        });

        $refreshDashboardSummary = fn (): mixed => app(DashboardCacheService::class)->refreshSummaryVersion();

        foreach ([Lesson::class, Homework::class, Announcement::class, IssueReport::class] as $model) {
            $model::saved($refreshDashboardSummary);
            $model::deleted($refreshDashboardSummary);

            if (method_exists($model, 'restored')) {
                $model::restored($refreshDashboardSummary);
            }
        }
    }

    private function rateLimitKey(Request $request): string
    {
        return ((string) ($request->user()?->id ?? 'guest')).'|'.$request->ip();
    }
}
