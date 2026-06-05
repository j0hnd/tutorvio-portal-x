<?php

namespace App\Policies;

use App\Models\LearningResource;
use App\Models\User;

class LearningResourcePolicy
{
    /**
     * Determine whether the user can list learning resources.
     *
     * Admins, teachers, and students can list resources. Staff need
     * `learning_resources.view`. Row-level visibility is enforced by the
     * resource query layer.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'teacher', 'student'])
            || ($user->hasRole('staff') && $user->can('learning_resources.view'));
    }

    /**
     * Determine whether the user can view a learning resource.
     *
     * Admins and staff with `learning_resources.view` can view any resource.
     * Teachers and students must pass the model's `visibleTo` assignment rules.
     * Resources not visible through that scope are denied.
     */
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

    /**
     * Determine whether the user can create a learning resource.
     *
     * Admins can create resources. Staff need `learning_resources.create`.
     * Teachers and students are denied by this policy.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('learning_resources.create'));
    }

    /**
     * Determine whether the user can update a learning resource.
     *
     * Admins can update resources. Staff need `learning_resources.update`.
     * Teachers and students are denied even when assigned to the resource.
     */
    public function update(User $user, LearningResource $learningResource): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('learning_resources.update'));
    }

    /**
     * Determine whether the user can delete a learning resource.
     *
     * Admins can delete resources. Staff need `learning_resources.delete`.
     * Teachers and students are denied.
     */
    public function delete(User $user, LearningResource $learningResource): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('learning_resources.delete'));
    }

    /**
     * Determine whether the user can assign a learning resource.
     *
     * Admins can assign resources. Staff need `learning_resources.update`.
     * Teachers and students are denied by this policy.
     */
    public function assign(User $user, LearningResource $learningResource): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('learning_resources.update'));
    }

    /**
     * Determine whether the user can view learning-resource version history.
     *
     * Version history is limited to admins and staff with
     * `learning_resources.view`. Assigned teachers/students are denied this
     * administrative history view.
     */
    public function viewVersionHistory(User $user, LearningResource $learningResource): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('learning_resources.view'));
    }
}
