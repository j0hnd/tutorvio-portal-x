<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserStatusHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'old_status',
        'new_status',
        'reason',
        'changed_by',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }

    /**
     * Get the user inverse relationship for this user status history.
     *
     * This user-facing relationship resolves one User model.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the changed by inverse relationship for this user status history.
     *
     * This admin/internal relationship resolves one User model.
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
