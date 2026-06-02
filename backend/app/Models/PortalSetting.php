<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortalSetting extends Model
{
    use HasFactory;

    public const TYPE_STRING = 'string';

    public const TYPE_INTEGER = 'integer';

    public const TYPE_BOOLEAN = 'boolean';

    public const TYPE_JSON = 'json';

    public const VALUE_TYPES = [
        self::TYPE_STRING,
        self::TYPE_INTEGER,
        self::TYPE_BOOLEAN,
        self::TYPE_JSON,
    ];

    protected $fillable = [
        'key',
        'category',
        'value',
        'value_type',
        'description',
        'is_public',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'is_public' => 'boolean',
        ];
    }

    /**
     * Get the updated by inverse relationship for this portal setting.
     *
     * This admin/internal relationship resolves one User model.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope the query to public records.
     *
     * Important query filters: is_public.
     *
     * @param  Builder<PortalSetting>  $query
     * @return Builder<PortalSetting>
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope the query to internal records.
     *
     * Important query filters: is_public.
     *
     * @param  Builder<PortalSetting>  $query
     * @return Builder<PortalSetting>
     */
    public function scopeInternal(Builder $query): Builder
    {
        return $query->where('is_public', false);
    }
}
