<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Announcement extends Model
{
    use HasFactory, HasPublicId, SoftDeletes;

    public const TYPE_ADMIN_ANNOUNCEMENT = 'admin_announcement';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SCHEDULED,
        self::STATUS_PUBLISHED,
        self::STATUS_ARCHIVED,
    ];

    protected $fillable = [
        'title',
        'body',
        'status',
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

    /**
     * Get the author inverse relationship for this announcement.
     *
     * This user-facing relationship resolves one User model.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Get the archived by inverse relationship for this announcement.
     *
     * This admin/internal relationship resolves one User model.
     */
    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    /**
     * Get the targets one-to-many relationship for this announcement.
     *
     * This user-facing relationship resolves multiple AnnouncementTarget models.
     */
    public function targets(): HasMany
    {
        return $this->hasMany(AnnouncementTarget::class);
    }

    /**
     * Get the read states one-to-many relationship for this announcement.
     *
     * This user-facing relationship resolves multiple AnnouncementReadState models.
     */
    public function readStates(): HasMany
    {
        return $this->hasMany(AnnouncementReadState::class);
    }

    /**
     * Get the recipients one-to-many relationship for this announcement.
     *
     * This user-facing relationship resolves multiple AnnouncementRecipient models.
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(AnnouncementRecipient::class);
    }

    /**
     * Scope the query to active records.
     *
     * Important query filters: status, is_archived, published_at.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_PUBLISHED)
            ->where('is_archived', false)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * Scope the query to visible to records.
     *
     * Important query filters: user_id, recipients relation.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('recipients', fn ($query) => $query->where('user_id', $user->id));
    }
}
