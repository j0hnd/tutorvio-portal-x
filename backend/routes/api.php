<?php

use App\Http\Controllers\Api\Admin\HomeworkSummaryController;
use App\Http\Controllers\Api\Admin\UserManagementController;
use App\Http\Controllers\Api\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\Auth\InvitationController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\ResetPasswordController;
use App\Http\Controllers\Api\CourseCatalog\CourseProgramController;
use App\Http\Controllers\Api\CourseCatalog\CourseTypeController;
use App\Http\Controllers\Api\Dashboard\DashboardController;
use App\Http\Controllers\Api\HomeworkController;
use App\Http\Controllers\Api\LearningResourceController;
use App\Http\Controllers\Api\LessonJoinController;
use App\Http\Controllers\Api\LessonNoteController;
use App\Http\Controllers\Api\LessonRecordController;
use App\Http\Controllers\Api\Profile\ProfileController;
use App\Http\Controllers\Api\Scheduling\CalendarController;
use App\Http\Controllers\Api\Scheduling\ClassScheduleController;
use App\Http\Controllers\Api\Scheduling\HolidayController;
use App\Http\Controllers\Api\Scheduling\LessonBookingController;
use App\Http\Controllers\Api\Scheduling\ScheduleReminderController;
use App\Http\Controllers\Api\Scheduling\TeacherAvailabilityController;
use App\Http\Controllers\Api\Scheduling\TeacherUnavailableDateController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', function () {
        return response()->json(['status' => 'ok']);
    });

    Route::prefix('auth')->group(function () {
        Route::post('login', [LoginController::class, 'login']);
        Route::post('register', [RegisterController::class, 'register']);
        Route::post('forgot-password', [ForgotPasswordController::class, 'forgotPassword']);
        Route::post('reset-password', [ResetPasswordController::class, 'resetPassword']);
        Route::get('accept-invitation/{token}', [InvitationController::class, 'accept']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [LoginController::class, 'logout']);
            Route::post('invite', [InvitationController::class, 'invite'])
                ->middleware(['role:admin|staff', 'permission:users.create']);
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', function (Request $request) {
            return $request->user();
        });

        Route::get('/profile', [ProfileController::class, 'show']);
        Route::patch('/profile', [ProfileController::class, 'update']);
        Route::get('/users/{user}/profile', [ProfileController::class, 'showUser']);
        Route::patch('/users/{user}/profile', [ProfileController::class, 'updateUser']);
        Route::get('/dashboard', DashboardController::class);
        Route::get('/lessons/{lesson}/join', LessonJoinController::class);
        Route::get('/lessons/{lesson}/lesson-notes', [LessonNoteController::class, 'byLesson']);
        Route::get('/students/{student}/lesson-notes', [LessonNoteController::class, 'byStudent']);
        Route::get('/lesson-notes/pending', [LessonNoteController::class, 'pending']);
        Route::apiResource('lesson-notes', LessonNoteController::class)
            ->only(['index', 'store', 'show', 'update'])
            ->parameters(['lesson-notes' => 'lessonNote']);
        Route::post('/lesson-records/{lessonRecord}/cancel', [LessonRecordController::class, 'cancel']);
        Route::apiResource('lesson-records', LessonRecordController::class)
            ->parameters(['lesson-records' => 'lessonRecord']);
        Route::post('/learning-resources/files', [LearningResourceController::class, 'storeFile']);
        Route::post('/learning-resources/links', [LearningResourceController::class, 'storeLink']);
        Route::get('/homeworks', [HomeworkController::class, 'index']);
        Route::post('/homeworks', [HomeworkController::class, 'store']);
        Route::get('/homeworks/{homework}', [HomeworkController::class, 'show']);
        Route::patch('/homeworks/{homework}/progress', [HomeworkController::class, 'updateProgress']);
        Route::patch('/homeworks/{homework}/review', [HomeworkController::class, 'review']);
        Route::post('/learning-resources/{learningResource}/students', [LearningResourceController::class, 'assignStudent']);
        Route::delete('/learning-resources/{learningResource}/students/{student}', [LearningResourceController::class, 'unassignStudent']);
        Route::post('/learning-resources/{learningResource}/lessons', [LearningResourceController::class, 'assignLesson']);
        Route::delete('/learning-resources/{learningResource}/lessons/{lesson}', [LearningResourceController::class, 'unassignLesson']);
        Route::get('/learning-resources/{learningResource}/download', [LearningResourceController::class, 'download']);
        Route::get('/learning-resources/{learningResource}/versions', [LearningResourceController::class, 'versions']);
        Route::apiResource('learning-resources', LearningResourceController::class)
            ->only(['index', 'show', 'update', 'destroy'])
            ->parameters(['learning-resources' => 'learningResource']);

        Route::post('/course-types/{courseType}/archive', [CourseTypeController::class, 'archive']);
        Route::apiResource('course-types', CourseTypeController::class)
            ->parameters(['course-types' => 'courseType']);
        Route::post('/course-programs/{courseProgram}/archive', [CourseProgramController::class, 'archive']);
        Route::post('/course-programs/{courseProgram}/learning-resources', [CourseProgramController::class, 'attachLearningResources']);
        Route::delete('/course-programs/{courseProgram}/learning-resources/{learningResource}', [CourseProgramController::class, 'detachLearningResource']);
        Route::apiResource('course-programs', CourseProgramController::class)
            ->parameters(['course-programs' => 'courseProgram']);

        Route::prefix('admin')->middleware(['role:admin', 'permission:admin.access'])->group(function () {
            Route::get('/access', function () {
                return response()->json(['status' => 'ok']);
            });
        });

        Route::prefix('admin')->middleware(['role:admin|staff'])->group(function () {
            Route::get('/users', function () {
                return response()->json(['status' => 'ok']);
            })->middleware('permission:users.view');

            Route::get('/homeworks/summary', HomeworkSummaryController::class)
                ->middleware('permission:homeworks.view');
        });

        Route::prefix('users')->middleware('role:admin|staff')->group(function () {
            Route::get('/', [UserManagementController::class, 'index'])
                ->middleware('permission:users.view');
            Route::post('/', [UserManagementController::class, 'store'])
                ->middleware('permission:users.create');
            Route::get('/{user}', [UserManagementController::class, 'show'])
                ->middleware('permission:users.view');
            Route::match(['put', 'patch'], '/{user}', [UserManagementController::class, 'update'])
                ->middleware('permission:users.update');
            Route::post('/{user}/activate', [UserManagementController::class, 'activate'])
                ->middleware('permission:users.activate');
            Route::post('/{user}/deactivate', [UserManagementController::class, 'deactivate'])
                ->middleware('permission:users.deactivate');
            Route::post('/{user}/roles', [UserManagementController::class, 'syncRoles'])
                ->middleware('permission:users.assign_roles');
            Route::get('/{user}/status-history', [UserManagementController::class, 'statusHistory'])
                ->middleware('permission:users.view');
        });

        Route::prefix('scheduling')->group(function () {
            Route::get('calendar', CalendarController::class);
            Route::post('lesson-bookings', [LessonBookingController::class, 'store'])->middleware('role:student');

            Route::post('class-schedules/recurring', [ClassScheduleController::class, 'recurring']);
            Route::apiResource('class-schedules', ClassScheduleController::class)
                ->parameters(['class-schedules' => 'classSchedule']);
            Route::post('class-schedules/{classSchedule}/cancel', [ClassScheduleController::class, 'cancel']);
            Route::post('class-schedules/{classSchedule}/reschedule', [ClassScheduleController::class, 'reschedule']);
            Route::patch('class-schedules/{classSchedule}/status', [ClassScheduleController::class, 'status']);

            Route::apiResource('teacher-availabilities', TeacherAvailabilityController::class)
                ->parameters(['teacher-availabilities' => 'teacherAvailability']);
            Route::apiResource('teacher-unavailable-dates', TeacherUnavailableDateController::class)
                ->parameters(['teacher-unavailable-dates' => 'teacherUnavailableDate']);
            Route::apiResource('holidays', HolidayController::class);
            Route::apiResource('schedule-reminders', ScheduleReminderController::class)
                ->parameters(['schedule-reminders' => 'scheduleReminder']);
        });
    });
});
