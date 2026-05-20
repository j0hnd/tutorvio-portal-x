<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\Material;
use App\Models\StudentProfile;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    private const MANAGED_ROLES = ['admin', 'staff', 'teacher', 'student'];

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        $role = $this->dashboardRole($user);
        $permissions = $user->getAllPermissions()->pluck('name')->sort()->values()->all();

        return [
            'role' => $role,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'permissions' => $permissions,
            'summary' => match ($role) {
                'admin' => $this->adminSummary(),
                'staff' => $this->staffSummary($user),
                'teacher' => $this->teacherSummary($user),
                'student' => $this->studentSummary($user),
                default => [],
            },
            'sections' => match ($role) {
                'admin' => ['users', 'students', 'classes'],
                'staff' => $this->staffSections($user),
                'teacher' => ['students', 'classes'],
                'student' => ['classes', 'materials', 'subscription'],
                default => [],
            },
        ];
    }

    private function dashboardRole(User $user): ?string
    {
        foreach (self::MANAGED_ROLES as $role) {
            if ($user->hasRole($role)) {
                return $role;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function adminSummary(): array
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $paymentAlertWindowEnd = now()->addDays(7)->endOfDay();

        $activeStudents = User::role('student')
            ->where('status', User::STATUS_ACTIVE)
            ->count();
        $activeTeachers = User::role('teacher')
            ->where('status', User::STATUS_ACTIVE)
            ->count();
        $todaysClasses = Lesson::whereBetween('start_time', [$todayStart, $todayEnd])->count();
        $missedClasses = Lesson::query()
            ->where(function (Builder $query) {
                $query->whereIn('status', ['missed', 'no_show'])
                    ->orWhereHas('attendances', fn (Builder $query) => $query->whereIn('status', ['absent', 'no_show']));
            })
            ->count();
        $pendingTeacherNotes = StudentProfile::query()
            ->whereHas('user', fn (Builder $query) => $query->where('status', User::STATUS_ACTIVE))
            ->where(function (Builder $query) {
                $query->whereNull('teacher_notes')
                    ->orWhere('teacher_notes', '');
            })
            ->count();
        $assignedStudents = StudentProfile::whereNotNull('assigned_teacher_id')->count();

        return [
            'operations' => [
                'active_students' => $activeStudents,
                'active_teachers' => $activeTeachers,
                'todays_classes' => $todaysClasses,
                'missed_classes' => $missedClasses,
                'pending_teacher_notes' => $pendingTeacherNotes,
            ],
            'users' => [
                'total' => User::count(),
                'active' => User::where('status', User::STATUS_ACTIVE)->count(),
                'invited' => User::where('status', User::STATUS_INVITED)->count(),
                'inactive' => User::where('status', User::STATUS_INACTIVE)->count(),
                'suspended' => User::where('status', User::STATUS_SUSPENDED)->count(),
            ],
            'students' => [
                'total' => User::role('student')->count(),
                'active' => $activeStudents,
                'assigned' => $assignedStudents,
                'unassigned' => User::role('student')
                    ->whereDoesntHave('studentProfile', fn ($query) => $query->whereNotNull('assigned_teacher_id'))
                    ->count(),
            ],
            'teachers' => [
                'total' => User::role('teacher')->count(),
                'active' => $activeTeachers,
            ],
            'classes' => $this->classSummary(Lesson::query()),
            'enrollments' => [
                'total_students' => User::role('student')->count(),
                'active_students' => $activeStudents,
                'invited_students' => User::role('student')->where('status', User::STATUS_INVITED)->count(),
                'inactive_students' => User::role('student')->where('status', User::STATUS_INACTIVE)->count(),
                'suspended_students' => User::role('student')->where('status', User::STATUS_SUSPENDED)->count(),
                'assigned_students' => $assignedStudents,
                'unassigned_students' => User::role('student')
                    ->whereDoesntHave('studentProfile', fn ($query) => $query->whereNotNull('assigned_teacher_id'))
                    ->count(),
                'new_this_month' => User::role('student')
                    ->where('created_at', '>=', now()->startOfMonth())
                    ->count(),
            ],
            'payment_package_alerts' => [
                'expired_subscriptions' => Subscription::whereNotNull('ends_at')
                    ->where('ends_at', '<', now())
                    ->count(),
                'expiring_within_7_days' => Subscription::where('status', 'active')
                    ->whereBetween('ends_at', [now(), $paymentAlertWindowEnd])
                    ->count(),
                'inactive_subscriptions' => Subscription::where('status', '!=', 'active')->count(),
                // TODO: Include unpaid invoices/package balances when billing tables exist.
                'unpaid_invoices' => 0,
                'low_lesson_balance' => 0,
            ],
            // TODO: Return persisted operational announcements when an announcements table exists.
            'operational_announcements' => [],
            'quick_links' => [
                'user_management',
                'student_management',
                'teacher_management',
                'class_management',
                'enrollments',
                'payments',
                'packages',
                'announcements',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function staffSummary(User $user): array
    {
        $summary = [];
        $widgets = [];

        if ($user->can('users.view')) {
            $summary['users'] = [
                'total' => User::count(),
                'active' => User::where('status', User::STATUS_ACTIVE)->count(),
                'invited' => User::where('status', User::STATUS_INVITED)->count(),
            ];
            $widgets[] = [
                'key' => 'users',
                'label' => 'Users',
                'data' => $summary['users'],
            ];
        }

        if ($user->can('students.view')) {
            $summary['students'] = [
                'total' => User::role('student')->count(),
                'assigned' => StudentProfile::whereNotNull('assigned_teacher_id')->count(),
                'unassigned' => User::role('student')
                    ->whereDoesntHave('studentProfile', fn ($query) => $query->whereNotNull('assigned_teacher_id'))
                    ->count(),
            ];
            $widgets[] = [
                'key' => 'students',
                'label' => 'Students',
                'data' => $summary['students'],
            ];
        }

        if ($user->can('classes.view')) {
            $summary['classes'] = $this->classSummary(Lesson::query());
            $widgets[] = [
                'key' => 'classes',
                'label' => 'Classes',
                'data' => $summary['classes'],
            ];
        }

        if ($widgets !== []) {
            $summary['dashboard_widgets'] = $widgets;
        }

        if ($user->can('dashboard.tasks.view')) {
            // TODO: Return persisted staff tasks when a task table exists.
            $summary['assigned_tasks'] = [];
        }

        if ($user->can('dashboard.operational_notices.view')) {
            // TODO: Return persisted operational notices when a notice table exists.
            $summary['operational_notices'] = [];
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    private function teacherSummary(User $user): array
    {
        $assignedStudentIds = StudentProfile::where('assigned_teacher_id', $user->id)
            ->pluck('user_id');

        $teacherLessons = Lesson::query()
            ->where('teacher_id', $user->id)
            ->whereIn('student_id', $assignedStudentIds);

        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        $todaysSchedule = (clone $teacherLessons)
            ->with('student.studentProfile')
            ->whereBetween('start_time', [$todayStart, $todayEnd])
            ->orderBy('start_time')
            ->get();

        $upcomingClasses = (clone $teacherLessons)
            ->with('student.studentProfile')
            ->where('start_time', '>', $todayEnd)
            ->orderBy('start_time')
            ->limit(10)
            ->get();

        $studentsNeedingNotes = StudentProfile::query()
            ->with('user:id,name,email,status')
            ->where('assigned_teacher_id', $user->id)
            ->where(function (Builder $query) {
                $query->whereNull('teacher_notes')
                    ->orWhere('teacher_notes', '');
            })
            ->orderBy('id')
            ->limit(10)
            ->get();

        $assignedStudents = StudentProfile::query()
            ->with('user:id,name,email,status')
            ->where('assigned_teacher_id', $user->id)
            ->orderBy('id')
            ->limit(20)
            ->get();

        $documentationShortcuts = (clone $teacherLessons)
            ->with('student.studentProfile')
            ->where('status', 'completed')
            ->orderByDesc('start_time')
            ->limit(10)
            ->get();

        return [
            'students' => [
                'assigned' => $assignedStudentIds->count(),
            ],
            'classes' => $this->classSummary($teacherLessons),
            'todays_schedule' => $todaysSchedule
                ->map(fn (Lesson $lesson) => $this->teacherLessonPayload($lesson))
                ->all(),
            'upcoming_classes' => $upcomingClasses
                ->map(fn (Lesson $lesson) => $this->teacherLessonPayload($lesson))
                ->all(),
            'students_needing_notes_or_follow_up' => $studentsNeedingNotes
                ->map(fn (StudentProfile $profile) => $this->teacherStudentProfilePayload($profile))
                ->all(),
            // TODO: Return teacher-visible lesson submissions when a submissions/homework table exists.
            'recent_lesson_submissions' => [],
            // TODO: Return teacher-targeted admin announcements when an announcements table exists.
            'admin_announcements' => [],
            'assigned_student_profiles' => $assignedStudents
                ->map(fn (StudentProfile $profile) => $this->teacherStudentProfilePayload($profile))
                ->all(),
            // TODO: Replace with lesson documentation records when a dedicated documentation table exists.
            'lesson_documentation_shortcuts' => $documentationShortcuts
                ->map(fn (Lesson $lesson) => [
                    ...$this->teacherLessonPayload($lesson),
                    'documentation_url' => null,
                    'needs_documentation' => blank($lesson->notes),
                ])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function studentSummary(User $user): array
    {
        $profile = $user->studentProfile()->with('assignedTeacher')->first();
        $subscription = Subscription::where('user_id', $user->id)
            ->latest('ends_at')
            ->latest()
            ->first();

        $upcomingLessons = Lesson::with('teacher:id,name')
            ->where('student_id', $user->id)
            ->where('start_time', '>=', now())
            ->orderBy('start_time')
            ->limit(5)
            ->get();

        $materials = Material::query()
            ->select('materials.id', 'materials.title', 'materials.description', 'materials.url', 'student_materials.assigned_at', 'student_materials.completed_at')
            ->join('student_materials', 'materials.id', '=', 'student_materials.material_id')
            ->where('student_materials.student_id', $user->id)
            ->orderByDesc('student_materials.assigned_at')
            ->limit(5)
            ->get();

        $assignedMaterials = DB::table('student_materials')->where('student_id', $user->id)->count();
        $completedMaterials = DB::table('student_materials')
            ->where('student_id', $user->id)
            ->whereNotNull('completed_at')
            ->count();

        return [
            'classes' => $this->classSummary(Lesson::where('student_id', $user->id)),
            'upcoming_lessons' => $upcomingLessons
                ->map(fn (Lesson $lesson) => $this->studentLessonPayload($lesson))
                ->all(),
            'next_lesson' => $upcomingLessons->first()
                ? $this->studentLessonJoinPayload($upcomingLessons->first())
                : null,
            'latest_teacher_note' => $profile?->teacher_notes,
            'learning_progress' => [
                'completed_lessons' => Lesson::where('student_id', $user->id)->where('status', 'completed')->count(),
                'scheduled_lessons' => Lesson::where('student_id', $user->id)->where('status', 'scheduled')->count(),
                'completed_materials' => $completedMaterials,
                'assigned_materials' => $assignedMaterials,
            ],
            'assigned_course' => [
                'course' => $profile?->course,
                'english_level' => $profile?->english_level,
                'current_level' => $profile?->current_level,
                'class_type' => $profile?->class_type,
                'assigned_teacher' => $profile?->assignedTeacher ? [
                    'id' => $profile->assignedTeacher->id,
                    'name' => $profile->assignedTeacher->name,
                ] : null,
            ],
            // TODO: Return student-scoped reminders/announcements when those tables are added.
            'reminders' => [],
            'announcements' => [],
            'lesson_balance' => null, // TODO: Populate when lesson credit/balance tracking exists.
            'active_plan' => $subscription ? [
                'status' => $subscription->status,
                'plan_name' => $subscription->plan_name,
                'starts_at' => $subscription->starts_at,
                'ends_at' => $subscription->ends_at,
            ] : null,
            'materials' => [
                'assigned' => $assignedMaterials,
                'completed' => $completedMaterials,
                'available' => max(0, $assignedMaterials - $completedMaterials),
            ],
            'recent_materials' => $materials
                ->map(fn (Material $material) => [
                    'id' => $material->id,
                    'title' => $material->title,
                    'description' => $material->description,
                    'url' => $material->url,
                    'assigned_at' => $material->assigned_at,
                    'completed_at' => $material->completed_at,
                ])
                ->all(),
            'homework' => [], // TODO: Return student-scoped homework when a homework table exists.
            'subscription' => [
                'status' => $subscription?->status,
                'plan_name' => $subscription?->plan_name,
                'ends_at' => $subscription?->ends_at,
            ],
        ];
    }

    /**
     * @param  Builder<Lesson>  $query
     * @return array<string, int>
     */
    private function classSummary(Builder $query): array
    {
        return [
            'total' => (clone $query)->count(),
            'scheduled' => (clone $query)->where('status', 'scheduled')->count(),
            'completed' => (clone $query)->where('status', 'completed')->count(),
            'cancelled' => (clone $query)->where('status', 'cancelled')->count(),
            'upcoming' => (clone $query)->where('start_time', '>=', now())->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function studentLessonPayload(Lesson $lesson): array
    {
        return [
            'id' => $lesson->id,
            'start_time' => $lesson->start_time,
            'end_time' => $lesson->end_time,
            'status' => $lesson->status,
            'teacher' => $lesson->teacher ? [
                'id' => $lesson->teacher->id,
                'name' => $lesson->teacher->name,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function teacherLessonPayload(Lesson $lesson): array
    {
        return [
            'id' => $lesson->id,
            'start_time' => $lesson->start_time,
            'end_time' => $lesson->end_time,
            'status' => $lesson->status,
            'student' => $lesson->student ? [
                'id' => $lesson->student->id,
                'name' => $lesson->student->name,
                'email' => $lesson->student->email,
                'profile' => $lesson->student->studentProfile
                    ? $this->teacherStudentProfilePayload($lesson->student->studentProfile, includeUser: false)
                    : null,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function teacherStudentProfilePayload(StudentProfile $profile, bool $includeUser = true): array
    {
        $payload = [
            'profile_id' => $profile->id,
            'student_id' => $profile->user_id,
            'course' => $profile->course,
            'english_level' => $profile->english_level,
            'current_level' => $profile->current_level,
            'class_type' => $profile->class_type,
            'start_date' => $profile->start_date,
            'teacher_notes' => $profile->teacher_notes,
            'preferences' => $profile->preferences,
            'goals' => $profile->goals,
            'learning_concerns' => $profile->learning_concerns,
        ];

        if ($includeUser) {
            $payload['student'] = $profile->user ? [
                'id' => $profile->user->id,
                'name' => $profile->user->name,
                'email' => $profile->user->email,
                'status' => $profile->user->status,
            ] : null;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function studentLessonJoinPayload(Lesson $lesson): array
    {
        return [
            ...$this->studentLessonPayload($lesson),
            'join_url' => null, // TODO: Populate when lesson meeting/join fields exist.
            'join_starts_at' => $lesson->start_time,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function staffSections(User $user): array
    {
        $sections = [];

        if ($user->can('users.view')) {
            $sections[] = 'users';
        }

        if ($user->can('students.view')) {
            $sections[] = 'students';
        }

        if ($user->can('classes.view')) {
            $sections[] = 'classes';
        }

        if ($user->can('dashboard.tasks.view')) {
            $sections[] = 'assigned_tasks';
        }

        if ($user->can('dashboard.operational_notices.view')) {
            $sections[] = 'operational_notices';
        }

        return $sections;
    }
}
