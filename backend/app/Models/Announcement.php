<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Announcement extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_ADMIN_ANNOUNCEMENT = 'admin_announcement';

    protected $fillable = [
        'title',
        'body',
        'type',
        'author_id',
        'scheduled_at',
        'published_at',
        'expires_at',
        'is_archived',
        'archived_at',
        'archived_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'is_archived' => 'boolean',
            'archived_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(AnnouncementTarget::class);
    }

    public function readStates(): HasMany
    {
        return $this->hasMany(AnnouncementReadState::class);
    }
}
