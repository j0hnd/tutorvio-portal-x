<?php

use App\Http\Controllers\Api\AcademicRecordController;
use App\Http\Controllers\Api\Admin\ActiveStudentsReportController;
use App\Http\Controllers\Api\Admin\AnnouncementController as AdminAnnouncementController;
use App\Http\Controllers\Api\Admin\AttendanceReportController;
use App\Http\Controllers\Api\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Api\Admin\ClassOversightController;
use App\Http\Controllers\Api\Admin\FormTemplateController as AdminFormTemplateController;
use App\Http\Controllers\Api\Admin\HomeworkSummaryController;
use App\Http\Controllers\Api\Admin\IssueReportController as AdminIssueReportController;
use App\Http\Controllers\Api\Admin\LessonCompletionReportController;
use App\Http\Controllers\Api\Admin\MissedClassReportController;
use App\Http\Controllers\Api\Admin\PackageUsageReportController;
use App\Http\Controllers\Api\Admin\PayoutPeriodController;
use App\Http\Controllers\Api\Admin\PayoutReportController;
use App\Http\Controllers\Api\Admin\PortalSettingController as AdminPortalSettingController;
use App\Http\Controllers\Api\Admin\RetentionContinuationReportController;
use App\Http\Controllers\Api\Admin\ScheduleChangeRequestController as AdminScheduleChangeRequestController;
use App\Http\Controllers\Api\Admin\SchoolReportController;
use App\Http\Controllers\Api\Admin\StudentProgressReportController as AdminStudentProgressReportController;
use App\Http\Controllers\Api\Admin\SubscriptionManagementController;
use App\Http\Controllers\Api\Admin\TeacherChangeRequestController as AdminTeacherChangeRequestController;
use App\Http\Controllers\Api\Admin\TeacherCompensationController;
use App\Http\Controllers\Api\Admin\TeacherCompensationRateRuleController;
use App\Http\Controllers\Api\Admin\TeacherEarningController as AdminTeacherEarningController;
use App\Http\Controllers\Api\Admin\TeacherNoteCompletionReportController as AdminTeacherNoteCompletionReportController;
use App\Http\Controllers\Api\Admin\TeacherPayoutAdjustmentController as AdminTeacherPayoutAdjustmentController;
use App\Http\Controllers\Api\Admin\TeacherStudentAssignmentController;
use App\Http\Controllers\Api\Admin\TrialEnrollmentReportController;
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
use App\Http\Controllers\Api\FormTemplateController;
use App\Http\Controllers\Api\HomeworkController;
use App\Http\Controllers\Api\IssueReportController;
use App\Http\Controllers\Api\LearningResourceController;
use App\Http\Controllers\Api\LessonJoinController;
use App\Http\Controllers\Api\LessonNoteController;
use App\Http\Controllers\Api\LessonRecordController;
use App\Http\Controllers\Api\Messages\MessageThreadController;
use App\Http\Controllers\Api\Notifications\NotificationController;
use App\Http\Controllers\Api\PortalMetadataController;
use App\Http\Controllers\Api\PortalSettingController;
use App\Http\Controllers\Api\Profile\ProfileController;
use App\Http\Controllers\Api\PublicPortalSettingController;
use App\Http\Controllers\Api\ScheduleChangeRequestController;
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
use App\Http\Controllers\Api\StudentProgressReportController;
use App\Http\Controllers\Api\TeacherChangeRequestController;
use App\Http\Controllers\Api\TeacherEarningController;
use App\Http\Controllers\Api\TeacherLoadReportController;
use App\Http\Controllers\Api\TeacherNoteCompletionReportController;
use App\Http\Controllers\Api\TeacherPayoutAdjustmentController;
use App\Http\Controllers\Api\TeacherWorkloadController;
use App\Http\Resources\Profile\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', function () {
        return response()->json(['status' => 'ok']);
    });

    Route::prefix('auth')->group(function () {
        Route::post('login', [LoginController::class, 'login'])->middleware('throttle:auth-login');
        Route::post('register', [RegisterController::class, 'register'])->middleware('throttle:auth-register');
        Route::post('forgot-password', [ForgotPasswordController::class, 'forgotPassword'])->middleware('throttle:auth-password-reset');
        Route::post('reset-password', [ResetPasswordController::class, 'resetPassword'])->middleware('throttle:auth-password-reset');
        Route::get('accept-invitation/{token}', [InvitationController::class, 'accept'])->middleware('throttle:auth-invitation-accept');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [LoginController::class, 'logout']);
            Route::post('invite', [InvitationController::class, 'invite'])
                ->middleware(['throttle:auth-invite', 'role:admin|staff', 'permission:users.create']);
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', function (Request $request) {
            return new UserResource($request->user()->loadMissing(['studentProfile', 'teacherProfile', 'staffProfile']));
        });

        Route::get('/profile', [ProfileController::class, 'show']);
        Route::patch('/profile', [ProfileController::class, 'update']);
        Route::get('/users/{user:public_id}/profile', [ProfileController::class, 'showUser']);
        Route::patch('/users/{user:public_id}/profile', [ProfileController::class, 'updateUser']);
        Route::get('/dashboard', DashboardController::class);
        Route::get('/metadata', PortalMetadataController::class);
        Route::get('/portal-settings', [PortalSettingController::class, 'index']);
        Route::get('/form-templates', [FormTemplateController::class, 'index']);
        Route::get('/form-templates/{formTemplate:public_id}', [FormTemplateController::class, 'show']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::get('/notifications/history', [NotificationController::class, 'history'])
            ->middleware(['role:admin|staff', 'permission:notifications.history.view']);
        Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])
            ->middleware('throttle:api-action');
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/{notification:public_id}', [NotificationController::class, 'show']);
        Route::post('/notifications/{notification:public_id}/read', [NotificationController::class, 'markRead'])
            ->middleware('throttle:api-action');
        Route::get('/message-threads/unread-count', [MessageThreadController::class, 'unreadCount']);
        Route::get('/message-threads', [MessageThreadController::class, 'index']);
        Route::post('/message-threads', [MessageThreadController::class, 'store'])
            ->middleware('throttle:api-action');
        Route::get('/message-threads/{messageThread:public_id}/messages', [MessageThreadController::class, 'messages']);
        Route::post('/message-threads/{messageThread:public_id}/messages', [MessageThreadController::class, 'send'])
            ->middleware('throttle:api-action');
        Route::post('/message-threads/{messageThread:public_id}/read', [MessageThreadController::class, 'markRead'])
            ->middleware('throttle:api-action');
        Route::get('/announcements/unread-count', [AnnouncementController::class, 'unreadCount']);
        Route::post('/announcements/mark-all-read', [AnnouncementController::class, 'markAllRead'])
            ->middleware('throttle:api-action');
        Route::get('/announcements', [AnnouncementController::class, 'index']);
        Route::get('/announcements/{announcement:public_id}', [AnnouncementController::class, 'show']);
        Route::post('/announcements/{announcement:public_id}/read', [AnnouncementController::class, 'markRead'])
            ->middleware('throttle:api-action');
        Route::post('/announcements/{announcement:public_id}/unread', [AnnouncementController::class, 'markUnread'])
            ->middleware('throttle:api-action');
        Route::post('/issue-reports', [IssueReportController::class, 'store'])
            ->middleware('throttle:api-action');
        Route::get('/issue-reports/{issueReport:public_id}', [IssueReportController::class, 'show']);
        Route::get('/lessons/{lesson:public_id}/join', LessonJoinController::class)
            ->middleware('throttle:api-lesson-join');
        Route::get('/lessons/{lesson:public_id}/lesson-notes', [LessonNoteController::class, 'byLesson']);
        Route::get('/students/{student:public_id}/lesson-notes', [LessonNoteController::class, 'byStudent']);
        Route::get('/students/{student:public_id}/progress-summary', [StudentProgressRecordController::class, 'summary']);
        Route::get('/students/{student:public_id}/progress-timeline', [StudentProgressRecordController::class, 'timeline']);
        Route::get('/students/{student:public_id}/invoices', [InvoiceController::class, 'history']);
        Route::get('/students/{student:public_id}/package-history', StudentPackageHistoryController::class);
        Route::get('/students/{student:public_id}/package-summary', StudentPackageSummaryController::class);
        Route::get('/students/{student:public_id}/assigned-teacher', [TeacherStudentAssignmentController::class, 'currentTeacher']);
        Route::get('/students/{student:public_id}/teacher-assignment-history', [TeacherStudentAssignmentController::class, 'studentHistory']);
        Route::get('/teachers/{teacher:public_id}/assigned-students', [TeacherStudentAssignmentController::class, 'teacherStudents']);
        Route::get('/teachers/{teacher:public_id}/student-assignment-history', [TeacherStudentAssignmentController::class, 'teacherHistory']);
        Route::get('/lesson-notes/pending', [LessonNoteController::class, 'pending']);
        Route::apiResource('lesson-notes', LessonNoteController::class)
            ->only(['index', 'store', 'show', 'update'])
            ->parameters(['lesson-notes' => 'lessonNote'])
            ->scoped(['lessonNote' => 'public_id']);
        Route::post('/lesson-records/{lessonRecord:public_id}/cancel', [LessonRecordController::class, 'cancel']);
        Route::apiResource('lesson-records', LessonRecordController::class)
            ->parameters(['lesson-records' => 'lessonRecord'])
            ->scoped(['lessonRecord' => 'public_id']);
        Route::apiResource('student-progress-records', StudentProgressRecordController::class)
            ->parameters(['student-progress-records' => 'studentProgressRecord'])
            ->scoped(['studentProgressRecord' => 'public_id']);
        Route::post('/academic-records/{academicRecord:public_id}/archive', [AcademicRecordController::class, 'archive']);
        Route::apiResource('academic-records', AcademicRecordController::class)
            ->only(['index', 'store', 'show', 'update'])
            ->parameters(['academic-records' => 'academicRecord'])
            ->scoped(['academicRecord' => 'public_id']);
        Route::post('/learning-resources/files', [LearningResourceController::class, 'storeFile'])
            ->middleware('throttle:api-upload');
        Route::post('/learning-resources/links', [LearningResourceController::class, 'storeLink']);
        Route::get('/homeworks', [HomeworkController::class, 'index']);
        Route::post('/homeworks', [HomeworkController::class, 'store']);
        Route::get('/homeworks/{homework:public_id}', [HomeworkController::class, 'show']);
        Route::patch('/homeworks/{homework:public_id}/progress', [HomeworkController::class, 'updateProgress']);
        Route::patch('/homeworks/{homework:public_id}/review', [HomeworkController::class, 'review']);
        Route::get('/teacher-earnings', [TeacherEarningController::class, 'index']);
        Route::get('/teacher-workloads', [TeacherWorkloadController::class, 'index']);
        Route::get('/teacher-workloads/{teacher:public_id}', [TeacherWorkloadController::class, 'show']);
        Route::get('/reports/teacher-load', TeacherLoadReportController::class)
            ->middleware('throttle:api-report');
        Route::get('/reports/student-progress', StudentProgressReportController::class)
            ->middleware('throttle:api-report');
        Route::get('/reports/teacher-note-completions', TeacherNoteCompletionReportController::class)
            ->middleware('throttle:api-report');
        Route::get('/payroll-adjustments', [TeacherPayoutAdjustmentController::class, 'index']);
        Route::get('/schedule-change-requests', [ScheduleChangeRequestController::class, 'index']);
        Route::post('/schedule-change-requests', [ScheduleChangeRequestController::class, 'store']);
        Route::get('/schedule-change-requests/{scheduleChangeRequest:public_id}', [ScheduleChangeRequestController::class, 'show']);
        Route::post('/schedule-change-requests/{scheduleChangeRequest:public_id}/cancel', [ScheduleChangeRequestController::class, 'cancel']);
        Route::get('/teacher-change-requests', [TeacherChangeRequestController::class, 'index'])
            ->middleware('role:student');
        Route::post('/teacher-change-requests', [TeacherChangeRequestController::class, 'store'])
            ->middleware('role:student');
        Route::get('/teacher-change-requests/{teacherChangeRequest:public_id}', [TeacherChangeRequestController::class, 'show'])
            ->middleware('role:student');
        Route::post('/teacher-change-requests/{teacherChangeRequest:public_id}/cancel', [TeacherChangeRequestController::class, 'cancel'])
            ->middleware('role:student');
        Route::post('/learning-resources/{learningResource:public_id}/students', [LearningResourceController::class, 'assignStudent']);
        Route::delete('/learning-resources/{learningResource:public_id}/students/{student:public_id}', [LearningResourceController::class, 'unassignStudent'])
            ->withoutScopedBindings();
        Route::post('/learning-resources/{learningResource:public_id}/lessons', [LearningResourceController::class, 'assignLesson']);
        Route::delete('/learning-resources/{learningResource:public_id}/lessons/{lesson:public_id}', [LearningResourceController::class, 'unassignLesson'])
            ->withoutScopedBindings();
        Route::get('/learning-resources/{learningResource:public_id}/download', [LearningResourceController::class, 'download'])
            ->middleware('throttle:api-download');
        Route::get('/learning-resources/{learningResource:public_id}/versions', [LearningResourceController::class, 'versions']);
        Route::apiResource('learning-resources', LearningResourceController::class)
            ->only(['index', 'show', 'update', 'destroy'])
            ->parameters(['learning-resources' => 'learningResource'])
            ->scoped(['learningResource' => 'public_id']);

        Route::post('/course-types/{courseType:public_id}/archive', [CourseTypeController::class, 'archive']);
        Route::apiResource('course-types', CourseTypeController::class)
            ->parameters(['course-types' => 'courseType'])
            ->scoped(['courseType' => 'public_id']);
        Route::post('/course-programs/{courseProgram:public_id}/archive', [CourseProgramController::class, 'archive']);
        Route::post('/course-programs/{courseProgram:public_id}/learning-resources', [CourseProgramController::class, 'attachLearningResources']);
        Route::delete('/course-programs/{courseProgram:public_id}/learning-resources/{learningResource:public_id}', [CourseProgramController::class, 'detachLearningResource'])
            ->withoutScopedBindings();
        Route::get('/course-programs/{courseProgram:public_id}/students', [CourseProgramStudentAssignmentController::class, 'courseStudents']);
        Route::post('/course-programs/{courseProgram:public_id}/students', [CourseProgramStudentAssignmentController::class, 'assignStudents']);
        Route::delete('/course-programs/{courseProgram:public_id}/students/{student:public_id}', [CourseProgramStudentAssignmentController::class, 'removeStudent'])
            ->withoutScopedBindings();
        Route::apiResource('course-programs', CourseProgramController::class)
            ->parameters(['course-programs' => 'courseProgram'])
            ->scoped(['courseProgram' => 'public_id']);
        Route::get('/students/{student:public_id}/course-programs', [CourseProgramStudentAssignmentController::class, 'studentCourses']);

        Route::prefix('admin')->middleware(['role:admin', 'permission:admin.access'])->group(function () {
            Route::get('/access', function () {
                return response()->json(['status' => 'ok']);
            });
        });

        Route::prefix('admin')->middleware(['role:admin|staff', 'permission:announcements.manage'])->group(function () {
            Route::post('/announcements/{announcement:public_id}/publish', [AdminAnnouncementController::class, 'publish']);
            Route::post('/announcements/{announcement:public_id}/unpublish', [AdminAnnouncementController::class, 'unpublish']);
            Route::post('/announcements/{announcement:public_id}/schedule', [AdminAnnouncementController::class, 'schedule']);
            Route::post('/announcements/{announcement:public_id}/archive', [AdminAnnouncementController::class, 'archive']);
            Route::get('/announcements/{announcement:public_id}/recipient-count', [AdminAnnouncementController::class, 'recipientCount']);
            Route::apiResource('announcements', AdminAnnouncementController::class)
                ->only(['index', 'store', 'show', 'update', 'destroy'])
                ->scoped(['announcement' => 'public_id']);
        });

        Route::prefix('admin')->middleware(['role:admin|staff'])->group(function () {
            Route::get('/users', function () {
                return response()->json(['status' => 'ok']);
            })->middleware('permission:users.view');

            Route::get('/homeworks/summary', HomeworkSummaryController::class)
                ->middleware('permission:homeworks.view');
            Route::get('/audit-logs', [AdminAuditLogController::class, 'index'])
                ->middleware('permission:audit_logs.view');
            // Admin-wide school management reports require explicit report access.
            Route::prefix('reports')->middleware(['permission:school_reports.view', 'throttle:api-report'])->group(function () {
                Route::get('/teacher-load', TeacherLoadReportController::class);
                Route::get('/school', [SchoolReportController::class, 'index']);
                Route::get('/active-students', ActiveStudentsReportController::class);
                Route::get('/attendance', AttendanceReportController::class);
                Route::get('/lesson-completions', LessonCompletionReportController::class);
                Route::get('/teacher-note-completions', AdminTeacherNoteCompletionReportController::class);
                Route::get('/student-progress', AdminStudentProgressReportController::class);
                Route::get('/missed-classes', MissedClassReportController::class);
                Route::get('/package-usage', PackageUsageReportController::class);
                Route::get('/retention-continuation', RetentionContinuationReportController::class);
                Route::get('/trial-enrollments', TrialEnrollmentReportController::class);
            });
            Route::get('/portal-settings', [AdminPortalSettingController::class, 'index'])
                ->middleware('permission:portal_settings.view');
            Route::match(['put', 'patch'], '/portal-settings', [AdminPortalSettingController::class, 'update'])
                ->middleware('permission:portal_settings.manage');
            Route::get('/form-templates', [AdminFormTemplateController::class, 'index'])
                ->middleware('permission:form_templates.view');
            Route::post('/form-templates', [AdminFormTemplateController::class, 'store'])
                ->middleware('permission:form_templates.manage');
            Route::get('/form-templates/{formTemplate:public_id}', [AdminFormTemplateController::class, 'show'])
                ->middleware('permission:form_templates.view');
            Route::match(['put', 'patch'], '/form-templates/{formTemplate:public_id}', [AdminFormTemplateController::class, 'update'])
                ->middleware('permission:form_templates.manage');
            Route::post('/form-templates/{formTemplate:public_id}/archive', [AdminFormTemplateController::class, 'archive'])
                ->middleware('permission:form_templates.manage');
            Route::get('/issue-reports', [AdminIssueReportController::class, 'index'])
                ->middleware('permission:issue_reports.view');
            Route::get('/issue-reports/{issueReport:public_id}', [AdminIssueReportController::class, 'show'])
                ->middleware('permission:issue_reports.view');
            Route::patch('/issue-reports/{issueReport:public_id}/status', [AdminIssueReportController::class, 'updateStatus'])
                ->middleware('permission:issue_reports.manage');
            Route::patch('/issue-reports/{issueReport:public_id}/assignment', [AdminIssueReportController::class, 'assign'])
                ->middleware('permission:issue_reports.assign');
            Route::post('/issue-reports/{issueReport:public_id}/resolution-notes', [AdminIssueReportController::class, 'addResolutionNote'])
                ->middleware('permission:issue_reports.resolve');
            Route::post('/issue-reports/{issueReport:public_id}/close', [AdminIssueReportController::class, 'close'])
                ->middleware('permission:issue_reports.manage');
            Route::post('/issue-reports/{issueReport:public_id}/cancel', [AdminIssueReportController::class, 'cancel'])
                ->middleware('permission:issue_reports.manage');

            Route::get('/classes', [ClassOversightController::class, 'index'])
                ->middleware('permission:classes.view');
            Route::get('/classes/{lesson:public_id}/teacher-notes', [ClassOversightController::class, 'teacherNotes'])
                ->middleware('permission:lesson_notes.view');
            Route::patch('/teacher-notes/{lessonNote:public_id}/review', [ClassOversightController::class, 'reviewTeacherNote'])
                ->middleware('permission:lesson_notes.update');

            Route::get('/teachers/{teacher:public_id}/teacher-compensations', [TeacherCompensationController::class, 'teacher'])
                ->middleware('permission:teacher_compensations.view');
            Route::get('/teachers/{teacher:public_id}/teacher-earnings', [AdminTeacherEarningController::class, 'teacher'])
                ->middleware('permission:teacher_earnings.view');
            Route::get('/teachers/{teacher:public_id}/payout-report', [PayoutReportController::class, 'teacher'])
                ->middleware('permission:payroll.view');
            Route::get('/teacher-student-assignments', [TeacherStudentAssignmentController::class, 'index'])
                ->middleware('permission:teacher_assignments.view');
            Route::get('/teacher-change-requests/pending', [AdminTeacherChangeRequestController::class, 'pending'])
                ->middleware('permission:teacher_change_requests.view');
            Route::get('/teacher-change-requests', [AdminTeacherChangeRequestController::class, 'index'])
                ->middleware('permission:teacher_change_requests.view');
            Route::get('/teacher-change-requests/{teacherChangeRequest:public_id}', [AdminTeacherChangeRequestController::class, 'show'])
                ->middleware('permission:teacher_change_requests.view');
            Route::post('/teacher-change-requests/{teacherChangeRequest:public_id}/approve', [AdminTeacherChangeRequestController::class, 'approve'])
                ->middleware('permission:teacher_change_requests.manage');
            Route::post('/teacher-change-requests/{teacherChangeRequest:public_id}/reject', [AdminTeacherChangeRequestController::class, 'reject'])
                ->middleware('permission:teacher_change_requests.manage');
            Route::get('/schedule-change-requests/pending', [AdminScheduleChangeRequestController::class, 'pending'])
                ->middleware('permission:schedule_change_requests.view');
            Route::get('/schedule-change-requests', [AdminScheduleChangeRequestController::class, 'index'])
                ->middleware('permission:schedule_change_requests.view');
            Route::get('/schedule-change-requests/{scheduleChangeRequest:public_id}', [AdminScheduleChangeRequestController::class, 'show'])
                ->middleware('permission:schedule_change_requests.view');
            Route::post('/schedule-change-requests/{scheduleChangeRequest:public_id}/approve', [AdminScheduleChangeRequestController::class, 'approve'])
                ->middleware('permission:schedule_change_requests.manage');
            Route::post('/schedule-change-requests/{scheduleChangeRequest:public_id}/reject', [AdminScheduleChangeRequestController::class, 'reject'])
                ->middleware('permission:schedule_change_requests.manage');
            Route::post('/teacher-student-assignments', [TeacherStudentAssignmentController::class, 'store'])
                ->middleware('permission:teacher_assignments.manage');
            Route::post('/students/{student:public_id}/teacher-assignment', [TeacherStudentAssignmentController::class, 'assignStudent'])
                ->middleware('permission:teacher_assignments.manage');
            Route::get('/students/{student:public_id}/available-teachers', [TeacherStudentAssignmentController::class, 'availableTeachers'])
                ->middleware('permission:teacher_assignments.view');
            Route::post('/students/{student:public_id}/teacher-assignment/reassign', [TeacherStudentAssignmentController::class, 'assignStudent'])
                ->middleware('permission:teacher_assignments.manage');
            Route::delete('/students/{student:public_id}/teacher-assignment', [TeacherStudentAssignmentController::class, 'endActive'])
                ->middleware('permission:teacher_assignments.manage');
            Route::get('/teacher-student-assignments/{teacherStudentAssignment:public_id}', [TeacherStudentAssignmentController::class, 'show'])
                ->middleware('permission:teacher_assignments.view');
            Route::match(['put', 'patch'], '/teacher-student-assignments/{teacherStudentAssignment:public_id}', [TeacherStudentAssignmentController::class, 'update'])
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
            Route::get('/payout-periods/{payoutPeriod:public_id}/report', [PayoutReportController::class, 'period'])
                ->middleware('permission:payroll.view');
            Route::get('/payout-periods/{payoutPeriod:public_id}', [PayoutPeriodController::class, 'show'])
                ->middleware('permission:payout_periods.view');
            Route::match(['put', 'patch'], '/payout-periods/{payoutPeriod:public_id}', [PayoutPeriodController::class, 'update'])
                ->middleware('permission:payout_periods.manage');
            Route::post('/teacher-compensations/{teacherCompensation:public_id}/archive', [TeacherCompensationController::class, 'archive'])
                ->middleware('permission:teacher_compensations.manage');
            Route::get('/teacher-compensations/{teacherCompensation:public_id}/rate-rules', [TeacherCompensationRateRuleController::class, 'index'])
                ->middleware('permission:teacher_compensations.view');
            Route::post('/teacher-compensations/{teacherCompensation:public_id}/rate-rules', [TeacherCompensationRateRuleController::class, 'store'])
                ->middleware('permission:teacher_compensations.manage');
            Route::match(['put', 'patch'], '/teacher-compensations/{teacherCompensation:public_id}/rate-rules/{teacherCompensationRateRule:public_id}', [TeacherCompensationRateRuleController::class, 'update'])
                ->middleware('permission:teacher_compensations.manage')
                ->withoutScopedBindings();
            Route::post('/teacher-compensations/{teacherCompensation:public_id}/rate-rules/{teacherCompensationRateRule:public_id}/archive', [TeacherCompensationRateRuleController::class, 'archive'])
                ->middleware('permission:teacher_compensations.manage')
                ->withoutScopedBindings();
            Route::delete('/teacher-compensations/{teacherCompensation:public_id}/rate-rules/{teacherCompensationRateRule:public_id}', [TeacherCompensationRateRuleController::class, 'destroy'])
                ->middleware('permission:teacher_compensations.manage')
                ->withoutScopedBindings();
            Route::get('/teacher-compensations', [TeacherCompensationController::class, 'index'])
                ->middleware('permission:teacher_compensations.view');
            Route::post('/teacher-compensations', [TeacherCompensationController::class, 'store'])
                ->middleware('permission:teacher_compensations.manage');
            Route::get('/teacher-compensations/{teacherCompensation:public_id}', [TeacherCompensationController::class, 'show'])
                ->middleware('permission:teacher_compensations.view');
            Route::match(['put', 'patch'], '/teacher-compensations/{teacherCompensation:public_id}', [TeacherCompensationController::class, 'update'])
                ->middleware('permission:teacher_compensations.manage');

            Route::get('/students/{student:public_id}/subscriptions/history', [SubscriptionManagementController::class, 'studentHistory'])
                ->middleware('permission:subscriptions.view');
            Route::post('/subscriptions/{subscription:public_id}/freeze', [SubscriptionManagementController::class, 'freeze'])
                ->middleware('permission:subscriptions.update');
            Route::post('/subscriptions/{subscription:public_id}/unfreeze', [SubscriptionManagementController::class, 'unfreeze'])
                ->middleware('permission:subscriptions.update');
            Route::patch('/subscriptions/{subscription:public_id}/payment-status', [SubscriptionManagementController::class, 'updatePaymentStatus'])
                ->middleware('permission:subscriptions.update');
            Route::patch('/subscriptions/{subscription:public_id}/status', [SubscriptionManagementController::class, 'updateStatus'])
                ->middleware('permission:subscriptions.update');
            Route::patch('/subscriptions/{subscription:public_id}/notes', [SubscriptionManagementController::class, 'updateNotes'])
                ->middleware('permission:subscriptions.update');
            Route::patch('/subscriptions/{subscription:public_id}/invoice-reference', [SubscriptionManagementController::class, 'updateInvoiceReference'])
                ->middleware('permission:subscriptions.update');
            Route::patch('/subscriptions/{subscription:public_id}/lesson-balance', [SubscriptionManagementController::class, 'adjustLessonBalance'])
                ->middleware('permission:subscriptions.update');
            Route::post('/subscriptions/{subscription:public_id}/renew', [SubscriptionManagementController::class, 'renew'])
                ->middleware('permission:subscriptions.create');
            Route::post('/subscriptions/{subscription:public_id}/cancel', [SubscriptionManagementController::class, 'cancel'])
                ->middleware('permission:subscriptions.delete');
            Route::post('/subscriptions/{subscription:public_id}/archive', [SubscriptionManagementController::class, 'archive'])
                ->middleware('permission:subscriptions.delete');
            Route::get('/subscriptions', [SubscriptionManagementController::class, 'index'])
                ->middleware('permission:subscriptions.view');
            Route::post('/subscriptions', [SubscriptionManagementController::class, 'store'])
                ->middleware('permission:subscriptions.create');
            Route::get('/subscriptions/{subscription:public_id}', [SubscriptionManagementController::class, 'show'])
                ->middleware('permission:subscriptions.view');
            Route::match(['put', 'patch'], '/subscriptions/{subscription:public_id}', [SubscriptionManagementController::class, 'update'])
                ->middleware('permission:subscriptions.update');
        });

        Route::prefix('invoices')->group(function () {
            Route::get('/', [InvoiceController::class, 'index']);
            Route::get('/{invoice:public_id}/download', [InvoiceController::class, 'download'])
                ->middleware('throttle:api-download');
            Route::get('/{invoice:public_id}', [InvoiceController::class, 'show']);
            Route::patch('/{invoice:public_id}/payment-status', [InvoiceController::class, 'updatePaymentStatus'])
                ->middleware(['role:admin|staff', 'permission:invoices.update']);
            Route::post('/{invoice:public_id}/send-email', [InvoiceController::class, 'sendEmail'])
                ->middleware(['role:admin|staff', 'permission:invoices.create', 'throttle:api-action']);
            Route::post('/generate', [InvoiceGenerationController::class, 'store'])
                ->middleware(['role:admin|staff', 'permission:invoices.create', 'throttle:api-action']);
        });

        Route::prefix('users')->middleware('role:admin|staff')->group(function () {
            Route::get('/', [UserManagementController::class, 'index'])
                ->middleware('permission:users.view');
            Route::post('/', [UserManagementController::class, 'store'])
                ->middleware('permission:users.create');
            Route::get('/{user:public_id}', [UserManagementController::class, 'show'])
                ->middleware('permission:users.view');
            Route::match(['put', 'patch'], '/{user:public_id}', [UserManagementController::class, 'update'])
                ->middleware('permission:users.update');
            Route::post('/{user:public_id}/activate', [UserManagementController::class, 'activate'])
                ->middleware('permission:users.activate');
            Route::post('/{user:public_id}/deactivate', [UserManagementController::class, 'deactivate'])
                ->middleware('permission:users.deactivate');
            Route::post('/{user:public_id}/roles', [UserManagementController::class, 'syncRoles'])
                ->middleware('permission:users.assign_roles');
            Route::get('/{user:public_id}/status-history', [UserManagementController::class, 'statusHistory'])
                ->middleware('permission:users.view');
        });

        Route::prefix('scheduling')->group(function () {
            Route::get('calendar', CalendarController::class);
            Route::post('lesson-bookings', [LessonBookingController::class, 'store'])
                ->middleware(['role:student', 'throttle:api-action']);

            Route::post('class-schedules/recurring', [ClassScheduleController::class, 'recurring'])
                ->middleware('throttle:api-action');
            Route::apiResource('class-schedules', ClassScheduleController::class)
                ->middleware('throttle:api-action')
                ->parameters(['class-schedules' => 'classSchedule'])
                ->scoped(['classSchedule' => 'public_id']);
            Route::post('class-schedules/{classSchedule:public_id}/cancel', [ClassScheduleController::class, 'cancel'])
                ->middleware('throttle:api-action');
            Route::post('class-schedules/{classSchedule:public_id}/reschedule', [ClassScheduleController::class, 'reschedule'])
                ->middleware('throttle:api-action');
            Route::patch('class-schedules/{classSchedule:public_id}/status', [ClassScheduleController::class, 'status'])
                ->middleware('throttle:api-action');

            Route::apiResource('teacher-availabilities', TeacherAvailabilityController::class)
                ->middleware('throttle:api-action')
                ->parameters(['teacher-availabilities' => 'teacherAvailability'])
                ->scoped(['teacherAvailability' => 'public_id']);
            Route::apiResource('teacher-unavailable-dates', TeacherUnavailableDateController::class)
                ->middleware('throttle:api-action')
                ->parameters(['teacher-unavailable-dates' => 'teacherUnavailableDate'])
                ->scoped(['teacherUnavailableDate' => 'public_id']);
            Route::apiResource('holidays', HolidayController::class)
                ->middleware('throttle:api-action')
                ->scoped(['holiday' => 'public_id']);
            Route::apiResource('schedule-reminders', ScheduleReminderController::class)
                ->middleware('throttle:api-action')
                ->parameters(['schedule-reminders' => 'scheduleReminder'])
                ->scoped(['scheduleReminder' => 'public_id']);
        });
    });
});

Route::get('/settings/public', PublicPortalSettingController::class)
    ->middleware('throttle:api-public');

Route::prefix('admin')
    ->middleware(['auth:sanctum', 'role:admin|staff'])
    ->group(function () {
        Route::get('/settings', [AdminPortalSettingController::class, 'index'])
            ->middleware('permission:portal_settings.view');
        Route::match(['put', 'patch'], '/settings', [AdminPortalSettingController::class, 'update'])
            ->middleware('permission:portal_settings.manage');
    });
