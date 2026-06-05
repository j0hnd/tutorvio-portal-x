<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class PublicIdResolver
{
    /**
     * Resolve an accepted public ULID reference to the numeric primary key used internally.
     *
     * @param  class-string<Model>  $modelClass
     */
    public static function toKey(mixed $value, string $modelClass): mixed
    {
        if (! is_string($value) || ! Str::isUlid($value)) {
            return $value;
        }

        return $modelClass::query()->where('public_id', $value)->value('id') ?? $value;
    }

    /**
     * Resolve multiple request fields from public IDs to internal keys.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, class-string<Model>>  $fields
     * @return array<string, mixed>
     */
    public static function resolveFields(array $input, array $fields): array
    {
        $resolved = [];

        foreach ($fields as $field => $modelClass) {
            if (! Arr::exists($input, $field)) {
                continue;
            }

            $resolved[$field] = self::toKey($input[$field], $modelClass);
        }

        return $resolved;
    }

    /**
     * Resolve a numeric key or public ID to a public ID for echoed filters.
     *
     * @param  class-string<Model>  $modelClass
     */
    public static function toPublicId(mixed $value, string $modelClass): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value) && ! is_numeric($value)) {
            return $value;
        }

        $publicId = $modelClass::query()->whereKey($value)->value('public_id');

        return $publicId === null ? null : (string) $publicId;
    }
}
