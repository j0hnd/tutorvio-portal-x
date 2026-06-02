<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_id',
        'student_id',
        'status',
    ];

    /**
     * Get the lesson inverse relationship for this attendance.
     *
     * This user-facing relationship resolves one Lesson model.
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * Get the student inverse relationship for this attendance.
     *
     * This user-facing relationship resolves one User model.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
