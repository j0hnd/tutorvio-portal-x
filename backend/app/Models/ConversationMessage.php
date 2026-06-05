<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConversationMessage extends Model
{
    use HasFactory, HasPublicId, SoftDeletes;

    public const STATUS_SENT = 'sent';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_READ = 'read';

    public const STATUS_FAILED = 'failed';

    public const STATUSES = [
        self::STATUS_SENT,
        self::STATUS_DELIVERED,
        self::STATUS_READ,
        self::STATUS_FAILED,
    ];

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'body',
        'links',
        'attachments',
        'status',
        'edited_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'links' => 'array',
            'attachments' => 'array',
            'edited_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (ConversationMessage $message): void {
            $message->pin()->delete();
        });
    }

    /**
     * Get the conversation inverse relationship for this message.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the sender inverse relationship for this message.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function attachmentRecords(): HasMany
    {
        return $this->hasMany(ConversationAttachment::class);
    }

    public function conversationAttachments(): HasMany
    {
        return $this->attachmentRecords();
    }

    public function pin(): HasOne
    {
        return $this->hasOne(ConversationMessagePin::class);
    }
}
