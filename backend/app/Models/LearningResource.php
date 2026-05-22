<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
                ->orWhere('course', 'like', $like)
                ->orWhere('level', 'like', $like)
                ->orWhere('original_filename', 'like', $like);
        });
    }

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user?->hasAnyRole(['admin', 'staff'])) {
            return $query;
        }

        if ($user?->hasRole('teacher')) {
            return $query->whereIn('visibility', [
                self::VISIBILITY_TEACHER_ONLY,
                self::VISIBILITY_STUDENT_VISIBLE,
                self::VISIBILITY_STUDENT_LIBRARY,
                self::VISIBILITY_PUBLIC,
            ]);
        }

        return $query->whereIn('visibility', [
            self::VISIBILITY_STUDENT_VISIBLE,
            self::VISIBILITY_STUDENT_LIBRARY,
            self::VISIBILITY_PUBLIC,
        ]);
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
}
