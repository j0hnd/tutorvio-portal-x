<?php

namespace App\Providers;

use App\Models\CourseProgram;
use App\Models\CourseType;
use App\Models\Homework;
use App\Models\LearningResource;
use App\Models\LessonNote;
use App\Models\LessonRecord;
use App\Models\Scheduling\ClassSchedule;
use App\Models\Scheduling\Holiday;
use App\Models\Scheduling\ScheduleReminder;
use App\Models\Scheduling\TeacherAvailability;
use App\Models\Scheduling\TeacherUnavailableDate;
use App\Models\StudentProgressRecord;
use App\Policies\ClassSchedulePolicy;
use App\Policies\CourseProgramPolicy;
use App\Policies\CourseTypePolicy;
use App\Policies\HolidayPolicy;
use App\Policies\HomeworkPolicy;
use App\Policies\LearningResourcePolicy;
use App\Policies\LessonNotePolicy;
use App\Policies\LessonRecordPolicy;
use App\Policies\ScheduleReminderPolicy;
use App\Policies\StudentProgressRecordPolicy;
use App\Policies\TeacherAvailabilityPolicy;
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
        Gate::policy(TeacherAvailability::class, TeacherAvailabilityPolicy::class);
        Gate::policy(TeacherUnavailableDate::class, TeacherAvailabilityPolicy::class);
        Gate::policy(Holiday::class, HolidayPolicy::class);
        Gate::policy(ScheduleReminder::class, ScheduleReminderPolicy::class);
        Gate::policy(LessonNote::class, LessonNotePolicy::class);
        Gate::policy(LessonRecord::class, LessonRecordPolicy::class);
        Gate::policy(StudentProgressRecord::class, StudentProgressRecordPolicy::class);
        Gate::policy(LearningResource::class, LearningResourcePolicy::class);
        Gate::policy(Homework::class, HomeworkPolicy::class);
        Gate::policy(CourseType::class, CourseTypePolicy::class);
        Gate::policy(CourseProgram::class, CourseProgramPolicy::class);
    }
}
