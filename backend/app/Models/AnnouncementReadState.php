<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementReadState extends Model
{
    use HasFactory;

    protected $fillable = [
        'announcement_id',
        'user_id',
        'read_at',
        'archived_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
