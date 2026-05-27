<?php

namespace App\Http\Requests\TeacherCompensations\Concerns;

use App\Models\TeacherCompensation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Validator;

trait ValidatesTeacherCompensationPayload
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('default_pay_rate') && $this->has('base_rate')) {
            $this->merge(['default_pay_rate' => $this->input('base_rate')]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $teacherId = $this->teacherIdForValidation();

            if ($teacherId > 0 && ! $validator->errors()->has('teacher_id') && ! $this->isTeacher($teacherId)) {
                $validator->errors()->add('teacher_id', 'The selected user must be a teacher.');
            }

            if ($validator->errors()->isNotEmpty()) {

                return;
            }

            $start = $this->input('effective_start_date', $this->route('teacherCompensation')?->effective_start_date?->toDateString());
            $end = $this->input('effective_end_date', $this->route('teacherCompensation')?->effective_end_date?->toDateString());

            if ($end !== null && strtotime((string) $end) <= strtotime((string) $start)) {
                $validator->errors()->add('effective_end_date', 'The effective end date must be after the effective start date.');

                return;
            }

            if ($this->conflictsWithActiveCompensation($teacherId)) {
                $validator->errors()->add(
                    'effective_start_date',
                    'The selected effective period conflicts with another active compensation setting for this teacher.'
                );
            }
        });
    }

    private function teacherIdForValidation(): int
    {
        return (int) $this->input(
            'teacher_id',
            $this->route('teacherCompensation')?->teacher_id
        );
    }

    private function isTeacher(int $teacherId): bool
    {
        return User::query()
            ->whereKey($teacherId)
            ->whereHas('roles', fn (Builder $query) => $query->where('name', 'teacher'))
            ->exists();
    }

    private function conflictsWithActiveCompensation(int $teacherId): bool
    {
        $start = $this->input('effective_start_date', $this->route('teacherCompensation')?->effective_start_date?->toDateString());
        $end = $this->input('effective_end_date', $this->route('teacherCompensation')?->effective_end_date?->toDateString());
        $ignoreId = $this->route('teacherCompensation')?->id;

        return TeacherCompensation::query()
            ->active()
            ->where('teacher_id', $teacherId)
            ->when($ignoreId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->whereDate('effective_start_date', '<=', $end ?? '9999-12-31')
            ->where(function (Builder $query) use ($start) {
                $query
                    ->whereNull('effective_end_date')
                    ->orWhereDate('effective_end_date', '>=', $start);
            })
            ->exists();
    }
}
