<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FormTemplate extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_ARCHIVED,
    ];

    protected $fillable = [
        'key',
        'name',
        'description',
        'template_type',
        'status',
        'version',
        'schema',
        'instructions',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'schema' => 'array',
            'version' => 'integer',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
