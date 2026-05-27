<?php

use App\Http\Controllers\Api\Admin\AnnouncementController as AdminAnnouncementController;
use App\Http\Controllers\Api\Admin\HomeworkSummaryController;
use App\Http\Controllers\Api\Admin\PayoutPeriodController;
use App\Http\Controllers\Api\Admin\PayoutReportController;
use App\Http\Controllers\Api\Admin\SubscriptionManagementController;
use App\Http\Controllers\Api\Admin\TeacherChangeRequestController as AdminTeacherChangeRequestController;
use App\Http\Controllers\Api\Admin\TeacherCompensationController;
use App\Http\Controllers\Api\Admin\TeacherCompensationRateRuleController;
use App\Http\Controllers\Api\Admin\TeacherEarningController as AdminTeacherEarningController;
use App\Http\Controllers\Api\Admin\TeacherPayoutAdjustmentController as AdminTeacherPayoutAdjustmentController;
use App\Http\Controllers\Api\Admin\TeacherStudentAssignmentController;
use App\Http\Controllers\Api\Admin\UserManagementController;
use App\Http\Controllers\Api\Announcements\AnnouncementController;
use App\Http\Controllers\Api\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\Auth\InvitationController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\ResetPasswordController;
use App\Http\Controllers\Api\Billing\InvoiceController;
use App\Http\Controllers\Api\Billing\InvoiceGenerationController;
use App\Http\Controllers\Api\CourseCatalog\CourseProgramController;
use App\Http\Controllers\Api\CourseCatalog\CourseProgramStudentAssignmentController;
use App\Http\Controllers\Api\CourseCatalog\CourseTypeController;
use App\Http\Controllers\Api\Dashboard\DashboardController;
use App\Http\Controllers\Api\HomeworkController;
use App\Http\Controllers\Api\LearningResourceController;
use App\Http\Controllers\Api\LessonJoinController;
use App\Http\Controllers\Api\LessonNoteController;
use App\Http\Controllers\Api\LessonRecordController;
use App\Http\Controllers\Api\Messages\MessageThreadController;
use App\Http\Controllers\Api\Notifications\NotificationController;
use App\Http\Controllers\Api\Profile\ProfileController;
use App\Http\Controllers\Api\Scheduling\CalendarController;
use App\Http\Controllers\Api\Scheduling\ClassScheduleController;
use App\Http\Controllers\Api\Scheduling\HolidayController;
use App\Http\Controllers\Api\Scheduling\LessonBookingController;
use App\Http\Controllers\Api\Scheduling\ScheduleReminderController;
use App\Http\Controllers\Api\Scheduling\TeacherAvailabilityController;
use App\Http\Controllers\Api\Scheduling\TeacherUnavailableDateController;
use App\Http\Controllers\Api\StudentPackageHistoryController;
use App\Http\Controllers\Api\StudentPackageSummaryController;
use App\Http\Controllers\Api\StudentProgressRecordController;
use App\Http\Controllers\Api\TeacherChangeRequestController;
use App\Http\Controllers\Api\TeacherEarningController;
use App\Http\Controllers\Api\TeacherPayoutAdjustmentController;
use App\Http\Controllers\Api\TeacherWorkloadController;
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
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::get('/notifications/history', [NotificationController::class, 'history'])
            ->middleware(['role:admin|staff', 'permission:notifications.history.view']);
        Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead']);
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/{notification}', [NotificationController::class, 'show']);
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
        Route::get('/message-threads/unread-count', [MessageThreadController::class, 'unreadCount']);
        Route::get('/message-threads', [MessageThreadController::class, 'index']);
        Route::post('/message-threads', [MessageThreadController::class, 'store']);
        Route::get('/message-threads/{messageThread}/messages', [MessageThreadController::class, 'messages']);
        Route::post('/message-threads/{messageThread}/messages', [MessageThreadController::class, 'send']);
        Route::post('/message-threads/{messageThread}/read', [MessageThreadController::class, 'markRead']);
        Route::get('/announcements', [AnnouncementController::class, 'index']);
        Route::get('/announcements/{announcement}', [AnnouncementController::class, 'show']);
        Route::get('/lessons/{lesson}/join', LessonJoinController::class);
        Route::get('/lessons/{lesson}/lesson-notes', [LessonNoteController::class, 'byLesson']);
        Route::get('/students/{student}/lesson-notes', [LessonNoteController::class, 'byStudent']);
        Route::get('/students/{student}/progress-summary', [StudentProgressRecordController::class, 'summary']);
        Route::get('/students/{student}/progress-timeline', [StudentProgressRecordController::class, 'timeline']);
        Route::get('/students/{student}/invoices', [InvoiceController::class, 'history']);
        Route::get('/students/{student}/package-history', StudentPackageHistoryController::class);
        Route::get('/students/{student}/package-summary', StudentPackageSummaryController::class);
        Route::get('/students/{student}/assigned-teacher', [TeacherStudentAssignmentController::class, 'currentTeacher']);
        Route::get('/students/{student}/teacher-assignment-history', [TeacherStudentAssignmentController::class, 'studentHistory']);
        Route::get('/teachers/{teacher}/assigned-students', [TeacherStudentAssignmentController::class, 'teacherStudents']);
        Route::get('/teachers/{teacher}/student-assignment-history', [TeacherStudentAssignmentController::class, 'teacherHistory']);
        Route::get('/lesson-notes/pending', [LessonNoteController::class, 'pending']);
        Route::apiResource('lesson-notes', LessonNoteController::class)
            ->only(['index', 'store', 'show', 'update'])
            ->parameters(['lesson-notes' => 'lessonNote']);
        Route::post('/lesson-records/{lessonRecord}/cancel', [LessonRecordController::class, 'cancel']);
        Route::apiResource('lesson-records', LessonRecordController::class)
            ->parameters(['lesson-records' => 'lessonRecord']);
        Route::apiResource('student-progress-records', StudentProgressRecordController::class)
            ->parameters(['student-progress-records' => 'studentProgressRecord']);
        Route::post('/learning-resources/files', [LearningResourceController::class, 'storeFile']);
        Route::post('/learning-resources/links', [LearningResourceController::class, 'storeLink']);
        Route::get('/homeworks', [HomeworkController::class, 'index']);
        Route::post('/homeworks', [HomeworkController::class, 'store']);
        Route::get('/homeworks/{homework}', [HomeworkController::class, 'show']);
        Route::patch('/homeworks/{homework}/progress', [HomeworkController::class, 'updateProgress']);
        Route::patch('/homeworks/{homework}/review', [HomeworkController::class, 'review']);
        Route::get('/teacher-earnings', [TeacherEarningController::class, 'index']);
        Route::get('/teacher-workloads', [TeacherWorkloadController::class, 'index']);
        Route::get('/teacher-workloads/{teacher}', [TeacherWorkloadController::class, 'show']);
        Route::get('/payroll-adjustments', [TeacherPayoutAdjustmentController::class, 'index']);
        Route::get('/teacher-change-requests', [TeacherChangeRequestController::class, 'index'])
            ->middleware('role:student');
        Route::post('/teacher-change-requests', [TeacherChangeRequestController::class, 'store'])
            ->middleware('role:student');
        Route::get('/teacher-change-requests/{teacherChangeRequest}', [TeacherChangeRequestController::class, 'show'])
            ->middleware('role:student');
        Route::post('/teacher-change-requests/{teacherChangeRequest}/cancel', [TeacherChangeRequestController::class, 'cancel'])
            ->middleware('role:student');
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
        Route::get('/course-programs/{courseProgram}/students', [CourseProgramStudentAssignmentController::class, 'courseStudents']);
        Route::post('/course-programs/{courseProgram}/students', [CourseProgramStudentAssignmentController::class, 'assignStudents']);
        Route::delete('/course-programs/{courseProgram}/students/{student}', [CourseProgramStudentAssignmentController::class, 'removeStudent']);
        Route::apiResource('course-programs', CourseProgramController::class)
            ->parameters(['course-programs' => 'courseProgram']);
        Route::get('/students/{student}/course-programs', [CourseProgramStudentAssignmentController::class, 'studentCourses']);

        Route::prefix('admin')->middleware(['role:admin', 'permission:admin.access'])->group(function () {
            Route::get('/access', function () {
                return response()->json(['status' => 'ok']);
            });
        });

        Route::prefix('admin')->middleware(['role:admin|staff', 'permission:announcements.manage'])->group(function () {
            Route::post('/announcements/{announcement}/publish', [AdminAnnouncementController::class, 'publish']);
            Route::post('/announcements/{announcement}/schedule', [AdminAnnouncementController::class, 'schedule']);
            Route::post('/announcements/{announcement}/archive', [AdminAnnouncementController::class, 'archive']);
            Route::get('/announcements/{announcement}/recipient-count', [AdminAnnouncementController::class, 'recipientCount']);
            Route::apiResource('announcements', AdminAnnouncementController::class)
                ->only(['index', 'store', 'show', 'update']);
        });

        Route::prefix('admin')->middleware(['role:admin|staff'])->group(function () {
            Route::get('/users', function () {
                return response()->json(['status' => 'ok']);
            })->middleware('permission:users.view');

            Route::get('/homeworks/summary', HomeworkSummaryController::class)
                ->middleware('permission:homeworks.view');

            Route::get('/teachers/{teacher}/teacher-compensations', [TeacherCompensationController::class, 'teacher'])
                ->middleware('permission:teacher_compensations.view');
            Route::get('/teachers/{teacher}/teacher-earnings', [AdminTeacherEarningController::class, 'teacher'])
                ->middleware('permission:teacher_earnings.view');
            Route::get('/teachers/{teacher}/payout-report', [PayoutReportController::class, 'teacher'])
                ->middleware('permission:payroll.view');
            Route::get('/teacher-student-assignments', [TeacherStudentAssignmentController::class, 'index'])
                ->middleware('permission:teacher_assignments.view');
            Route::get('/teacher-change-requests/pending', [AdminTeacherChangeRequestController::class, 'pending'])
                ->middleware('permission:teacher_change_requests.view');
            Route::get('/teacher-change-requests', [AdminTeacherChangeRequestController::class, 'index'])
                ->middleware('permission:teacher_change_requests.view');
            Route::get('/teacher-change-requests/{teacherChangeRequest}', [AdminTeacherChangeRequestController::class, 'show'])
                ->middleware('permission:teacher_change_requests.view');
            Route::post('/teacher-change-requests/{teacherChangeRequest}/approve', [AdminTeacherChangeRequestController::class, 'approve'])
                ->middleware('permission:teacher_change_requests.manage');
            Route::post('/teacher-change-requests/{teacherChangeRequest}/reject', [AdminTeacherChangeRequestController::class, 'reject'])
                ->middleware('permission:teacher_change_requests.manage');
            Route::post('/teacher-student-assignments', [TeacherStudentAssignmentController::class, 'store'])
                ->middleware('permission:teacher_assignments.manage');
            Route::post('/students/{student}/teacher-assignment', [TeacherStudentAssignmentController::class, 'assignStudent'])
                ->middleware('permission:teacher_assignments.manage');
            Route::get('/students/{student}/available-teachers', [TeacherStudentAssignmentController::class, 'availableTeachers'])
                ->middleware('permission:teacher_assignments.view');
            Route::post('/students/{student}/teacher-assignment/reassign', [TeacherStudentAssignmentController::class, 'assignStudent'])
                ->middleware('permission:teacher_assignments.manage');
            Route::delete('/students/{student}/teacher-assignment', [TeacherStudentAssignmentController::class, 'endActive'])
                ->middleware('permission:teacher_assignments.manage');
            Route::get('/teacher-student-assignments/{teacherStudentAssignment}', [TeacherStudentAssignmentController::class, 'show'])
                ->middleware('permission:teacher_assignments.view');
            Route::match(['put', 'patch'], '/teacher-student-assignments/{teacherStudentAssignment}', [TeacherStudentAssignmentController::class, 'update'])
                ->middleware('permission:teacher_assignments.manage');
            Route::get('/teacher-earnings', [AdminTeacherEarningController::class, 'index'])
                ->middleware('permission:teacher_earnings.view');
            Route::get('/payout-adjustments', [AdminTeacherPayoutAdjustmentController::class, 'index'])
                ->middleware('permission:payroll.view');
            Route::post('/payout-adjustments', [AdminTeacherPayoutAdjustmentController::class, 'store'])
                ->middleware('permission:payroll.manage');
            Route::get('/payout-periods', [PayoutPeriodController::class, 'index'])
                ->middleware('permission:payout_periods.view');
            Route::post('/payout-periods', [PayoutPeriodController::class, 'store'])
                ->middleware('permission:payout_periods.manage');
            Route::get('/payout-periods/{payoutPeriod}/report', [PayoutReportController::class, 'period'])
                ->middleware('permission:payroll.view');
            Route::get('/payout-periods/{payoutPeriod}', [PayoutPeriodController::class, 'show'])
                ->middleware('permission:payout_periods.view');
            Route::match(['put', 'patch'], '/payout-periods/{payoutPeriod}', [PayoutPeriodController::class, 'update'])
                ->middleware('permission:payout_periods.manage');
            Route::post('/teacher-compensations/{teacherCompensation}/archive', [TeacherCompensationController::class, 'archive'])
                ->middleware('permission:teacher_compensations.manage');
            Route::get('/teacher-compensations/{teacherCompensation}/rate-rules', [TeacherCompensationRateRuleController::class, 'index'])
                ->middleware('permission:teacher_compensations.view');
            Route::post('/teacher-compensations/{teacherCompensation}/rate-rules', [TeacherCompensationRateRuleController::class, 'store'])
                ->middleware('permission:teacher_compensations.manage');
            Route::match(['put', 'patch'], '/teacher-compensations/{teacherCompensation}/rate-rules/{teacherCompensationRateRule}', [TeacherCompensationRateRuleController::class, 'update'])
                ->middleware('permission:teacher_compensations.manage');
            Route::post('/teacher-compensations/{teacherCompensation}/rate-rules/{teacherCompensationRateRule}/archive', [TeacherCompensationRateRuleController::class, 'archive'])
                ->middleware('permission:teacher_compensations.manage');
            Route::delete('/teacher-compensations/{teacherCompensation}/rate-rules/{teacherCompensationRateRule}', [TeacherCompensationRateRuleController::class, 'destroy'])
                ->middleware('permission:teacher_compensations.manage');
            Route::get('/teacher-compensations', [TeacherCompensationController::class, 'index'])
                ->middleware('permission:teacher_compensations.view');
            Route::post('/teacher-compensations', [TeacherCompensationController::class, 'store'])
                ->middleware('permission:teacher_compensations.manage');
            Route::get('/teacher-compensations/{teacherCompensation}', [TeacherCompensationController::class, 'show'])
                ->middleware('permission:teacher_compensations.view');
            Route::match(['put', 'patch'], '/teacher-compensations/{teacherCompensation}', [TeacherCompensationController::class, 'update'])
                ->middleware('permission:teacher_compensations.manage');

            Route::get('/students/{student}/subscriptions/history', [SubscriptionManagementController::class, 'studentHistory'])
                ->middleware('permission:subscriptions.view');
            Route::post('/subscriptions/{subscription}/freeze', [SubscriptionManagementController::class, 'freeze'])
                ->middleware('permission:subscriptions.update');
            Route::post('/subscriptions/{subscription}/unfreeze', [SubscriptionManagementController::class, 'unfreeze'])
                ->middleware('permission:subscriptions.update');
            Route::patch('/subscriptions/{subscription}/payment-status', [SubscriptionManagementController::class, 'updatePaymentStatus'])
                ->middleware('permission:subscriptions.update');
            Route::patch('/subscriptions/{subscription}/status', [SubscriptionManagementController::class, 'updateStatus'])
                ->middleware('permission:subscriptions.update');
            Route::patch('/subscriptions/{subscription}/notes', [SubscriptionManagementController::class, 'updateNotes'])
                ->middleware('permission:subscriptions.update');
            Route::patch('/subscriptions/{subscription}/invoice-reference', [SubscriptionManagementController::class, 'updateInvoiceReference'])
                ->middleware('permission:subscriptions.update');
            Route::patch('/subscriptions/{subscription}/lesson-balance', [SubscriptionManagementController::class, 'adjustLessonBalance'])
                ->middleware('permission:subscriptions.update');
            Route::post('/subscriptions/{subscription}/renew', [SubscriptionManagementController::class, 'renew'])
                ->middleware('permission:subscriptions.create');
            Route::post('/subscriptions/{subscription}/cancel', [SubscriptionManagementController::class, 'cancel'])
                ->middleware('permission:subscriptions.delete');
            Route::post('/subscriptions/{subscription}/archive', [SubscriptionManagementController::class, 'archive'])
                ->middleware('permission:subscriptions.delete');
            Route::get('/subscriptions', [SubscriptionManagementController::class, 'index'])
                ->middleware('permission:subscriptions.view');
            Route::post('/subscriptions', [SubscriptionManagementController::class, 'store'])
                ->middleware('permission:subscriptions.create');
            Route::get('/subscriptions/{subscription}', [SubscriptionManagementController::class, 'show'])
                ->middleware('permission:subscriptions.view');
            Route::match(['put', 'patch'], '/subscriptions/{subscription}', [SubscriptionManagementController::class, 'update'])
                ->middleware('permission:subscriptions.update');
        });

        Route::prefix('invoices')->group(function () {
            Route::get('/', [InvoiceController::class, 'index']);
            Route::get('/{invoice}/download', [InvoiceController::class, 'download']);
            Route::get('/{invoice}', [InvoiceController::class, 'show']);
            Route::patch('/{invoice}/payment-status', [InvoiceController::class, 'updatePaymentStatus'])
                ->middleware(['role:admin|staff', 'permission:invoices.update']);
            Route::post('/{invoice}/send-email', [InvoiceController::class, 'sendEmail'])
                ->middleware(['role:admin|staff', 'permission:invoices.create']);
            Route::post('/generate', [InvoiceGenerationController::class, 'store'])
                ->middleware(['role:admin|staff', 'permission:invoices.create']);
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
