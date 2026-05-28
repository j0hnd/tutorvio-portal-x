<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OperationalAnnouncement extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PUBLISHED,
        self::STATUS_ARCHIVED,
    ];

    public const PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_NORMAL,
        self::PRIORITY_HIGH,
        self::PRIORITY_URGENT,
    ];

    protected $fillable = [
        'title',
        'body',
        'audience',
        'priority',
        'status',
        'published_by',
        'starts_at',
        'ends_at',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
        ];
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
