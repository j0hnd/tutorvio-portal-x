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
        $subscription = Subscription::where('user_id', $user->id)
            ->latest('ends_at')
            ->latest()
            ->first();

        return [
            'classes' => $this->classSummary(Lesson::where('student_id', $user->id)),
            'materials' => [
                'assigned' => DB::table('student_materials')->where('student_id', $user->id)->count(),
                'completed' => DB::table('student_materials')
                    ->where('student_id', $user->id)
                    ->whereNotNull('completed_at')
                    ->count(),
                'available' => Material::count(),
            ],
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
