<?php

namespace App\Http\Resources\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

trait SanitizesApiResponses
{
    protected function canViewAdminFields(Request $request, ?string $permission = null): bool
    {
        $user = $request->user();

        if ($user?->hasRole('admin') === true) {
            return true;
        }

        if ($user?->hasRole('staff') !== true) {
            return false;
        }

        return $permission === null || $user->can($permission);
    }

    protected function publicId(?Model $model): ?string
    {
        $publicId = $model?->getAttribute('public_id');

        return $publicId === null ? null : (string) $publicId;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function userSummary(?User $user, Request $request, bool $includeEmail = true): ?array
    {
        if ($user === null) {
            return null;
        }

        $data = [
            'id' => $this->publicId($user),
            'name' => $user->name,
        ];

        if ($includeEmail) {
            $data['email'] = $user->email;
        }

        if ($user->timezone !== null) {
            $data['timezone'] = $user->timezone;
        }

        if ($this->canViewAdminFields($request)) {
            $data['status'] = $user->status;
        }

        return $data;
    }
}
