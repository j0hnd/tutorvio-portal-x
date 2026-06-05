<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FormTemplate extends Model
{
    use HasFactory, HasPublicId, SoftDeletes;

    public const TYPE_STUDENT_ABSENCE_FORM = 'student_absence_form';

    public const TYPE_TEACHER_ABSENCE_FORM = 'teacher_absence_form';

    public const TYPE_MATERIAL_REQUEST_FORM = 'material_request_form';

    public const TYPE_CLASS_INCIDENT_FORM = 'class_incident_form';

    public const TYPE_SCHEDULE_CHANGE_REQUEST_FORM = 'schedule_change_request_form';

    public const TYPE_STUDENT_CONCERN_FORM = 'student_concern_form';

    public const TYPE_TEACHER_CONCERN_FORM = 'teacher_concern_form';

    public const TEMPLATE_TYPES = [
        self::TYPE_STUDENT_ABSENCE_FORM,
        self::TYPE_TEACHER_ABSENCE_FORM,
        self::TYPE_MATERIAL_REQUEST_FORM,
        self::TYPE_CLASS_INCIDENT_FORM,
        self::TYPE_SCHEDULE_CHANGE_REQUEST_FORM,
        self::TYPE_STUDENT_CONCERN_FORM,
        self::TYPE_TEACHER_CONCERN_FORM,
    ];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_ARCHIVED,
    ];

    protected $fillable = [
        'key',
        'name',
        'description',
        'template_type',
        'status',
        'version',
        'schema',
        'instructions',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'schema' => 'array',
            'version' => 'integer',
        ];
    }

    /**
     * Get the created by inverse relationship for this form template.
     *
     * This admin/internal relationship resolves one User model.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the updated by inverse relationship for this form template.
     *
     * This admin/internal relationship resolves one User model.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @return array<int, string>
     */
    public static function submitterRolesFor(string $templateType): array
    {
        return match ($templateType) {
            self::TYPE_STUDENT_ABSENCE_FORM,
            self::TYPE_TEACHER_CONCERN_FORM => ['student', 'admin', 'staff'],
            self::TYPE_TEACHER_ABSENCE_FORM,
            self::TYPE_STUDENT_CONCERN_FORM => ['teacher', 'admin', 'staff'],
            default => ['student', 'teacher', 'admin', 'staff'],
        };
    }
}
