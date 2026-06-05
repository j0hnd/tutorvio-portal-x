<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherCompensationRateRule extends Model
{
    use HasFactory, HasPublicId;

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

    /**
     * Get the teacher compensation inverse relationship for this teacher compensation rate rule.
     *
     * This admin/internal relationship resolves one TeacherCompensation model.
     */
    public function teacherCompensation(): BelongsTo
    {
        return $this->belongsTo(TeacherCompensation::class);
    }

    /**
     * Get the course type inverse relationship for this teacher compensation rate rule.
     *
     * This admin/internal relationship resolves one CourseType model.
     */
    public function courseType(): BelongsTo
    {
        return $this->belongsTo(CourseType::class);
    }

    /**
     * Get the course program inverse relationship for this teacher compensation rate rule.
     *
     * This admin/internal relationship resolves one CourseProgram model.
     */
    public function courseProgram(): BelongsTo
    {
        return $this->belongsTo(CourseProgram::class);
    }

    /**
     * Scope the query to active records.
     *
     * Important query filters: is_active.
     *
     * @param  Builder<TeacherCompensationRateRule>  $query
     * @return Builder<TeacherCompensationRateRule>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
