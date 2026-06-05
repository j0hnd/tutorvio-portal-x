<?php

namespace App\Policies;

use App\Models\User;

class AuditLogPolicy
{
    /**
     * Determine whether the user can list audit logs.
     *
     * Admins can view audit logs. Staff must have the Spatie permission
     * `audit_logs.view`. Teachers and students are denied.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('audit_logs.view'));
    }
}
