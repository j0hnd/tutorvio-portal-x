<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementTarget extends Model
{
    use HasFactory;

    public const TARGET_ALL = 'all';

    public const TARGET_ROLE = 'role';

    public const TARGET_USER = 'user';

    public const TARGET_COURSE_TYPE = 'course_type';

    public const TARGET_COURSE_PROGRAM = 'course_program';

    public const TARGET_CLASS_SCHEDULE = 'class_schedule';

    public const TARGETS = [
        self::TARGET_ALL,
        self::TARGET_ROLE,
        self::TARGET_USER,
        self::TARGET_COURSE_TYPE,
        self::TARGET_COURSE_PROGRAM,
        self::TARGET_CLASS_SCHEDULE,
    ];

    protected $fillable = [
        'announcement_id',
        'target_type',
        'target_id',
        'user_id',
        'role',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
