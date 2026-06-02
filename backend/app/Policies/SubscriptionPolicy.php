<?php

namespace App\Policies;

use App\Models\Subscription;
use App\Models\User;

class SubscriptionPolicy
{
    /**
     * Determine whether the user can list subscriptions.
     *
     * Admins can list subscriptions. Staff need the Spatie permission
     * `subscriptions.view`. Teachers and students are denied.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('subscriptions.view'));
    }

    /**
     * Determine whether the user can view a subscription.
     *
     * Detail access follows the list rule: admins are allowed and staff need
     * `subscriptions.view`. No ownership exception applies.
     */
    public function view(User $user, Subscription $subscription): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can create a subscription.
     *
     * Admins can create subscriptions. Staff need `subscriptions.create`.
     * Teachers and students are denied.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('subscriptions.create'));
    }

    /**
     * Determine whether the user can update a subscription.
     *
     * Admins can update subscriptions. Staff need `subscriptions.update`.
     * Teachers and students are denied.
     */
    public function update(User $user, Subscription $subscription): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('subscriptions.update'));
    }

    /**
     * Determine whether the user can update subscription payment status.
     *
     * Payment-status updates use the subscription update rule: admins are
     * allowed and staff require `subscriptions.update`.
     */
    public function updatePaymentStatus(User $user, Subscription $subscription): bool
    {
        return $this->update($user, $subscription);
    }

    /**
     * Determine whether the user can renew a subscription.
     *
     * Admins can renew subscriptions. Staff need `subscriptions.create`.
     * Teachers and students are denied.
     */
    public function renew(User $user, Subscription $subscription): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('subscriptions.create'));
    }

    /**
     * Determine whether the user can cancel a subscription.
     *
     * Admins can cancel subscriptions. Staff need `subscriptions.delete`.
     * Teachers and students are denied.
     */
    public function cancel(User $user, Subscription $subscription): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('subscriptions.delete'));
    }
}
