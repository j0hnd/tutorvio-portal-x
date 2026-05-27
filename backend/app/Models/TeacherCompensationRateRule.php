<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherCompensationRateRule extends Model
{
    use HasFactory;

    protected $table = 'teacher_compensation_rate_rules';

    protected $fillable = [
        'teacher_compensation_id',
        'lesson_type',
        'experience_level',
        'contract_agreement',
        'course_type_id',
        'course_program_id',
        'pay_model',
        'pay_rate',
        'currency',
        'priority',
        'is_active',
        'internal_admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'pay_rate' => 'decimal:2',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function teacherCompensation(): BelongsTo
    {
        return $this->belongsTo(TeacherCompensation::class);
    }

    public function courseType(): BelongsTo
    {
        return $this->belongsTo(CourseType::class);
    }

    public function courseProgram(): BelongsTo
    {
        return $this->belongsTo(CourseProgram::class);
    }

    /**
     * @param  Builder<TeacherCompensationRateRule>  $query
     * @return Builder<TeacherCompensationRateRule>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
