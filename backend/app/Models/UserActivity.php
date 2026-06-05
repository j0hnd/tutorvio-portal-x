<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'description',
        'ip_address',
    ];

    /**
     * Get the user inverse relationship for this user activity.
     *
     * This user-facing relationship resolves one User model.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
