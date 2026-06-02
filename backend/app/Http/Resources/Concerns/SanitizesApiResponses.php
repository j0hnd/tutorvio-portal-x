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
     * @param  class-string<Model>  $modelClass
     */
    protected function publicIdFor(string $modelClass, mixed $id): ?string
    {
        if ($id === null || $id === '') {
            return null;
        }

        if (is_string($id) && ! is_numeric($id)) {
            return $id;
        }

        return $modelClass::query()->whereKey($id)->value('public_id');
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
