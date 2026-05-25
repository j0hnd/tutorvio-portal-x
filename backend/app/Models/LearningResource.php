<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class LearningResource extends Model
{
    use HasFactory;

    public const TYPE_FILE = 'file';

    public const TYPE_LINK = 'link';

    public const TYPE_PDF = 'pdf';

    public const TYPE_WORKSHEET = 'worksheet';

    public const TYPE_SLIDE = 'slide';

    public const TYPE_DOCUMENT = 'document';

    public const RESOURCE_TYPES = [
        self::TYPE_FILE,
        self::TYPE_LINK,
        self::TYPE_PDF,
        self::TYPE_WORKSHEET,
        self::TYPE_SLIDE,
        self::TYPE_DOCUMENT,
    ];

    public const VISIBILITY_STUDENT_VISIBLE = 'student-visible';

    public const VISIBILITY_TEACHER_ONLY = 'teacher-only';

    public const VISIBILITY_ADMIN_ONLY = 'admin-only';

    public const VISIBILITY_STUDENT_LIBRARY = 'student-library';

    public const VISIBILITY_PUBLIC = 'public';

    public const VISIBILITIES = [
        self::VISIBILITY_STUDENT_VISIBLE,
        self::VISIBILITY_TEACHER_ONLY,
        self::VISIBILITY_ADMIN_ONLY,
        self::VISIBILITY_STUDENT_LIBRARY,
        self::VISIBILITY_PUBLIC,
    ];

    protected $fillable = [
        'title',
        'description',
        'resource_type',
        'storage_disk',
        'file_path',
        'url',
        'original_filename',
        'mime_type',
        'file_size',
        'preview_metadata',
        'course',
        'level',
        'visibility',
        'created_by',
        'current_version_number',
    ];

    protected $hidden = [
        'storage_disk',
        'file_path',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'preview_metadata' => 'array',
            'current_version_number' => 'integer',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedStudents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'learning_resource_student', 'learning_resource_id', 'student_id')
            ->withPivot(['assigned_by', 'assigned_at'])
            ->withTimestamps();
    }

    public function assignedLessons(): BelongsToMany
    {
        return $this->belongsToMany(Lesson::class, 'learning_resource_lesson', 'learning_resource_id', 'lesson_id')
            ->withPivot(['assigned_by', 'assigned_at'])
            ->withTimestamps();
    }

    public function assignedHomeworks(): BelongsToMany
    {
        return $this->belongsToMany(Homework::class, 'homework_learning_resource', 'learning_resource_id', 'homework_id')
            ->withPivot(['assigned_by', 'assigned_at'])
            ->withTimestamps();
    }

    public function coursePrograms(): BelongsToMany
    {
        return $this->belongsToMany(CourseProgram::class, 'course_program_learning_resource')
            ->withPivot(['attached_by', 'attached_at'])
            ->withTimestamps();
    }

    public function versions(): HasMany
    {
        return $this->hasMany(LearningResourceVersion::class)->orderByDesc('version_number');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term).'%';

        return $query->where(function (Builder $query) use ($like) {
            $query
                ->where('title', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhere('resource_type', 'like', $like)
                ->orWhere('course', 'like', $like)
                ->orWhere('level', 'like', $like)
                ->orWhere('original_filename', 'like', $like);
        });
    }

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user?->hasRole('admin')) {
            return $query;
        }

        if ($user?->hasRole('teacher')) {
            return $query
                ->where('visibility', '!=', self::VISIBILITY_ADMIN_ONLY)
                ->where(function (Builder $query) use ($user) {
                    $query
                        ->where('visibility', self::VISIBILITY_TEACHER_ONLY)
                        ->orWhere(function (Builder $query) {
                            $query
                                ->whereIn('visibility', self::studentVisibleVisibilities())
                                ->whereDoesntHave('assignedStudents')
                                ->whereDoesntHave('assignedLessons');
                        })
                        ->orWhereHas('assignedStudents.studentProfile', function (Builder $query) use ($user) {
                            $query->where('assigned_teacher_id', $user->id);
                        })
                        ->orWhereHas('assignedLessons', function (Builder $query) use ($user) {
                            $query->where('teacher_id', $user->id);
                        });
                });
        }

        if ($user?->hasRole('staff') && $user->can('learning_resources.view')) {
            return $query;
        }

        if ($user?->hasRole('student')) {
            return $query
                ->whereIn('visibility', self::studentVisibleVisibilities())
                ->where(function (Builder $query) use ($user) {
                    $query
                        ->where(function (Builder $query) {
                            $query
                                ->whereDoesntHave('assignedStudents')
                                ->whereDoesntHave('assignedLessons');
                        })
                        ->orWhereHas('assignedStudents', function (Builder $query) use ($user) {
                            $query->whereKey($user->id);
                        })
                        ->orWhereHas('assignedLessons', function (Builder $query) use ($user) {
                            $query->where('student_id', $user->id);
                        });
                });
        }

        return $query->whereRaw('1 = 0');
    }

    /**
     * @return array<int, string>
     */
    public static function studentVisibleVisibilities(): array
    {
        return [
            self::VISIBILITY_STUDENT_VISIBLE,
            self::VISIBILITY_STUDENT_LIBRARY,
            self::VISIBILITY_PUBLIC,
        ];
    }

    public function isExternalLink(): bool
    {
        return $this->resource_type === self::TYPE_LINK && $this->url !== null;
    }

    public function hasStoredFile(): bool
    {
        return $this->file_path !== null;
    }

    public function storedFileExists(): bool
    {
        return $this->hasStoredFile()
            && Storage::disk($this->storageDisk())->exists($this->file_path);
    }

    public function storageDisk(): string
    {
        return $this->storage_disk ?: (string) config('learning_resources.disk', config('filesystems.default'));
    }

    public function currentVersionNumber(): int
    {
        if ($this->current_version_number !== null) {
            return (int) $this->current_version_number;
        }

        return $this->hasStoredFile() ? 1 : 0;
    }
}
