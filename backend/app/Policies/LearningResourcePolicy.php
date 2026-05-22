<?php

namespace App\Policies;

use App\Models\LearningResource;
use App\Models\User;

class LearningResourcePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'teacher', 'student'])
            || ($user->hasRole('staff') && $user->can('learning_resources.view'));
    }

    public function view(User $user, LearningResource $learningResource): bool
    {
        if ($user->hasRole('admin') || ($user->hasRole('staff') && $user->can('learning_resources.view'))) {
            return true;
        }

        return LearningResource::query()
            ->whereKey($learningResource->getKey())
            ->visibleTo($user)
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('learning_resources.create'));
    }

    public function update(User $user, LearningResource $learningResource): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('learning_resources.update'));
    }

    public function delete(User $user, LearningResource $learningResource): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('learning_resources.delete'));
    }

    public function assign(User $user, LearningResource $learningResource): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('learning_resources.update'));
    }
}
