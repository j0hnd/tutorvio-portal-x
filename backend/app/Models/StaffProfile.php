<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'department',
        'access_limitations',
    ];

    /**
     * Get the user inverse relationship for this staff profile.
     *
     * This user-facing relationship resolves one User model.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the activities one-to-many relationship for this staff profile.
     *
     * This admin/internal relationship resolves multiple UserActivity models.
     */
    public function activities(): HasMany
    {
        return $this->hasMany(UserActivity::class, 'user_id', 'user_id');
    }
}
