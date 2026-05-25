<?php

namespace App\Policies;

use App\Models\CourseProgram;
use App\Models\User;

class CourseProgramPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, CourseProgram $courseProgram): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, CourseProgram $courseProgram): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, CourseProgram $courseProgram): bool
    {
        return $user->hasRole('admin');
    }
}
