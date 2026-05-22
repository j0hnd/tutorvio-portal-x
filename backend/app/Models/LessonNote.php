<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_id',
        'student_id',
        'teacher_id',
        'author_id',
        'lesson_record_id',
        'lesson_objective',
        'topics_covered',
        'vocabulary_learned',
        'grammar_focus',
        'pronunciation_issues',
        'student_speaking_confidence_observation',
        'homework_assignment',
        'recommendation_for_next_lesson',
        'internal_note',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'immutable_datetime',
        ];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function lessonRecord(): BelongsTo
    {
        return $this->belongsTo(LessonRecord::class);
    }
}
