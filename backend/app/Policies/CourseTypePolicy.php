<?php

namespace App\Policies;

use App\Models\CourseType;
use App\Models\User;

class CourseTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, CourseType $courseType): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, CourseType $courseType): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, CourseType $courseType): bool
    {
        return $user->hasRole('admin');
    }
}
