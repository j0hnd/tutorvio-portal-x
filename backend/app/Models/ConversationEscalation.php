<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConversationEscalation extends Model
{
    use HasFactory, HasPublicId, SoftDeletes;

    public const STATUS_OPEN = 'open';

    public const STATUS_IN_REVIEW = 'in_review';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_DISMISSED = 'dismissed';

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_IN_REVIEW,
        self::STATUS_RESOLVED,
        self::STATUS_DISMISSED,
    ];

    protected $fillable = [
        'conversation_id',
        'conversation_message_id',
        'escalated_by',
        'reviewed_by',
        'issue_report_id',
        'status',
        'reason',
        'notes',
        'review_notes',
        'reviewed_at',
        'resolved_at',
        'dismissed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
            'dismissed_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(ConversationMessage::class, 'conversation_message_id');
    }

    public function escalatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function issueReport(): BelongsTo
    {
        return $this->belongsTo(IssueReport::class);
    }
}
