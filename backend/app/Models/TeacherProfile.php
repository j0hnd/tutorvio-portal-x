<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'specialization',
        'bio',
        'expertise',
        'class_load',
        'teaching_availability',
        'performance_summary',
        'internal_status',
        'teaching_notes',
        'internal_remarks',
        'document_contract_status',
    ];

    protected function casts(): array
    {
        return [
            'teaching_availability' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedStudents()
    {
        return $this->hasMany(StudentProfile::class, 'assigned_teacher_id', 'user_id');
    }

    public function lessons()
    {
        return $this->hasMany(Lesson::class, 'teacher_id', 'user_id');
    }
}
