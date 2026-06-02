<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasPublicId
{
    protected static function bootHasPublicId(): void
    {
        static::creating(function (Model $model): void {
            if (blank($model->getAttribute('public_id'))) {
                $model->setAttribute('public_id', (string) Str::ulid());
            }
        });
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $query = $this->newQuery();

        if ($field !== null) {
            return $query->where($field, $value)->first();
        }

        if (Str::isUlid((string) $value)) {
            return $query->where('public_id', $value)->first();
        }

        return $query->where($this->getRouteKeyName(), $value)->first();
    }
}
