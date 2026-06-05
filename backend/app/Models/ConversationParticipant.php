<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConversationParticipant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'participant_role',
        'participant_role_snapshot',
        'participant_roles_snapshot',
        'joined_at',
        'last_read_at',
        'muted_at',
        'archived_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'participant_roles_snapshot' => 'array',
            'joined_at' => 'immutable_datetime',
            'last_read_at' => 'immutable_datetime',
            'muted_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the conversation inverse relationship for this participant.
     *
     * This user-facing relationship resolves one Conversation model.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the user inverse relationship for this participant.
     *
     * This user-facing relationship resolves one User model.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the student profile relationship for this participant.
     *
     * This user-facing relationship resolves one StudentProfile model.
     */
    public function studentProfile(): HasOne
    {
        return $this->hasOne(StudentProfile::class, 'user_id', 'user_id');
    }

    /**
     * Get the teacher profile relationship for this participant.
     *
     * This user-facing relationship resolves one TeacherProfile model.
     */
    public function teacherProfile(): HasOne
    {
        return $this->hasOne(TeacherProfile::class, 'user_id', 'user_id');
    }
}
