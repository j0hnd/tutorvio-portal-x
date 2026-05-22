<?php

namespace App\Policies;

use App\Models\User;

class HomeworkPolicy
{
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'teacher']);
    }
}
