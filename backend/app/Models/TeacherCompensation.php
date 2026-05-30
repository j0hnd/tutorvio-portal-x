<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeacherCompensation extends Model
{
    use HasFactory, HasPublicId;

    public const PAY_MODEL_PER_LESSON = 'per_lesson';

    public const PAY_MODEL_PER_HOUR = 'per_hour';

    public const PAY_MODEL_PER_STUDENT = 'per_student';

    public const PAY_MODEL_PER_COURSE = 'per_course';

    public const PAY_MODELS = [
        self::PAY_MODEL_PER_LESSON,
        self::PAY_MODEL_PER_HOUR,
        self::PAY_MODEL_PER_STUDENT,
        self::PAY_MODEL_PER_COURSE,
    ];

    protected $table = 'teacher_compensations';

    protected $fillable = [
        'teacher_id',
        'pay_model',
        'default_pay_rate',
        'currency',
        'effective_start_date',
        'effective_end_date',
        'internal_admin_notes',
        'archived_at',
        'archived_by',
    ];

    protected function casts(): array
    {
        return [
            'default_pay_rate' => 'decimal:2',
            'effective_start_date' => 'date',
            'effective_end_date' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function rateRules(): HasMany
    {
        return $this->hasMany(TeacherCompensationRateRule::class);
    }

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    /**
     * @param  Builder<TeacherCompensation>  $query
     * @return Builder<TeacherCompensation>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    /**
     * @param  Builder<TeacherCompensation>  $query
     * @return Builder<TeacherCompensation>
     */
    public function scopeEffectiveOn(Builder $query, string $date): Builder
    {
        return $query
            ->whereDate('effective_start_date', '<=', $date)
            ->where(function (Builder $query) use ($date) {
                $query
                    ->whereNull('effective_end_date')
                    ->orWhereDate('effective_end_date', '>=', $date);
            });
    }
}
