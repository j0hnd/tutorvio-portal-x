<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MessageTemplate extends Model
{
    use HasFactory, HasPublicId, SoftDeletes;

    public const CATEGORY_LESSON_REMINDER = 'lesson_reminder';

    public const CATEGORY_HOMEWORK_REMINDER = 'homework_reminder';

    public const CATEGORY_RESCHEDULE_NOTICE = 'reschedule_notice';

    public const CATEGORY_PAYMENT_REMINDER = 'payment_reminder';

    public const CATEGORY_ATTENDANCE_FOLLOW_UP = 'attendance_follow_up';

    public const CATEGORY_PROGRESS_CHECK_IN = 'progress_check_in';

    public const CATEGORIES = [
        self::CATEGORY_LESSON_REMINDER,
        self::CATEGORY_HOMEWORK_REMINDER,
        self::CATEGORY_RESCHEDULE_NOTICE,
        self::CATEGORY_PAYMENT_REMINDER,
        self::CATEGORY_ATTENDANCE_FOLLOW_UP,
        self::CATEGORY_PROGRESS_CHECK_IN,
    ];

    public const ROLE_ADMIN = 'admin';

    public const ROLE_STAFF = 'staff';

    public const ROLE_TEACHER = 'teacher';

    public const ROLE_STUDENT = 'student';

    public const ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_STAFF,
        self::ROLE_TEACHER,
        self::ROLE_STUDENT,
    ];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
    ];

    protected $fillable = [
        'title',
        'body',
        'category',
        'role_visibility',
        'status',
        'teacher_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'role_visibility' => 'array',
        ];
    }

    /**
     * Scope templates available for a specific user's role and ownership.
     */
    public function scopeVisibleToUser(Builder $query, User $user): Builder
    {
        $roles = $user->roles->pluck('name')
            ->intersect(self::ROLES)
            ->values()
            ->all();

        if ($roles === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where('status', self::STATUS_ACTIVE)
            ->where(function (Builder $query) use ($roles): void {
                foreach ($roles as $role) {
                    $query->orWhereJsonContains('role_visibility', $role);
                }
            })
            ->where(function (Builder $query) use ($user): void {
                $query
                    ->whereNull('teacher_id')
                    ->orWhere('teacher_id', $user->id);
            });
    }

    /**
     * Get the teacher-specific owner for this message template.
     *
     * This user-facing relationship resolves one User model.
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Get the creator for this message template.
     *
     * This admin/internal relationship resolves one User model.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the last updater for this message template.
     *
     * This admin/internal relationship resolves one User model.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
