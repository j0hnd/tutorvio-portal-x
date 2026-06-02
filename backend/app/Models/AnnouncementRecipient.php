<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementRecipient extends Model
{
    use HasFactory;

    protected $fillable = [
        'announcement_id',
        'user_id',
        'matched_targets',
    ];

    protected function casts(): array
    {
        return [
            'matched_targets' => 'array',
        ];
    }

    /**
     * Get the announcement inverse relationship for this announcement recipient.
     *
     * This user-facing relationship resolves one Announcement model.
     */
    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    /**
     * Get the user inverse relationship for this announcement recipient.
     *
     * This user-facing relationship resolves one User model.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
