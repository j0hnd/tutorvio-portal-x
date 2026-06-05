<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConversationAttachment extends Model
{
    use HasFactory, HasPublicId, SoftDeletes;

    public const TYPE_FILE = 'file';

    public const TYPE_LINK = 'link';

    protected $fillable = [
        'conversation_id',
        'conversation_message_id',
        'uploaded_by',
        'type',
        'title',
        'url',
        'storage_disk',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size',
        'metadata',
    ];

    protected $hidden = [
        'storage_disk',
        'file_path',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
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

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function hasStoredFile(): bool
    {
        return $this->type === self::TYPE_FILE && filled($this->storage_disk) && filled($this->file_path);
    }

    public function isPreviewable(): bool
    {
        return $this->hasStoredFile()
            && in_array($this->mime_type, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/gif'], true);
    }

    public function storageDisk(): string
    {
        return (string) ($this->storage_disk ?: config('chat_attachments.disk', config('filesystems.default')));
    }
}
