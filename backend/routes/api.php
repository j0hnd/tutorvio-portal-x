<?php

use App\Http\Controllers\Api\Admin\UserManagementController;
use App\Http\Controllers\Api\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\Auth\InvitationController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\ResetPasswordController;
use App\Http\Controllers\Api\Dashboard\DashboardController;
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

        Route::prefix('admin')->middleware(['role:admin', 'permission:admin.access'])->group(function () {
            Route::get('/access', function () {
                return response()->json(['status' => 'ok']);
            });
        });

        Route::prefix('admin')->middleware(['role:admin|staff'])->group(function () {
            Route::get('/users', function () {
                return response()->json(['status' => 'ok']);
            })->middleware('permission:users.view');
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
