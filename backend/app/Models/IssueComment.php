<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class IssueComment extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_COMMENT = 'comment';

    public const TYPE_RESOLUTION_NOTE = 'resolution_note';

    public const TYPE_STATUS_CHANGE = 'status_change';

    public const COMMENT_TYPES = [
        self::TYPE_COMMENT,
        self::TYPE_RESOLUTION_NOTE,
        self::TYPE_STATUS_CHANGE,
    ];

    protected $fillable = [
        'issue_report_id',
        'author_id',
        'comment_type',
        'body',
        'is_internal',
    ];

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
        ];
    }

    /**
     * Get the issue report inverse relationship for this issue comment.
     *
     * This user-facing relationship resolves one IssueReport model.
     */
    public function issueReport(): BelongsTo
    {
        return $this->belongsTo(IssueReport::class);
    }

    /**
     * Get the author inverse relationship for this issue comment.
     *
     * This user-facing relationship resolves one User model.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
