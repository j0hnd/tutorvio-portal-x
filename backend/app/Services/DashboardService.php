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
        return [
            'users' => [
                'total' => User::count(),
                'active' => User::where('status', User::STATUS_ACTIVE)->count(),
                'invited' => User::where('status', User::STATUS_INVITED)->count(),
                'inactive' => User::where('status', User::STATUS_INACTIVE)->count(),
                'suspended' => User::where('status', User::STATUS_SUSPENDED)->count(),
            ],
            'students' => [
                'total' => User::role('student')->count(),
                'assigned' => StudentProfile::whereNotNull('assigned_teacher_id')->count(),
                'unassigned' => User::role('student')
                    ->whereDoesntHave('studentProfile', fn ($query) => $query->whereNotNull('assigned_teacher_id'))
                    ->count(),
            ],
            'classes' => $this->classSummary(Lesson::query()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function staffSummary(User $user): array
    {
        $summary = [];

        if ($user->can('users.view')) {
            $summary['users'] = [
                'total' => User::count(),
                'active' => User::where('status', User::STATUS_ACTIVE)->count(),
                'invited' => User::where('status', User::STATUS_INVITED)->count(),
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
        }

        if ($user->can('classes.view')) {
            $summary['classes'] = $this->classSummary(Lesson::query());
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    private function teacherSummary(User $user): array
    {
        return [
            'students' => [
                'assigned' => StudentProfile::where('assigned_teacher_id', $user->id)->count(),
            ],
            'classes' => $this->classSummary(Lesson::where('teacher_id', $user->id)),
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

        return $sections;
    }
}
