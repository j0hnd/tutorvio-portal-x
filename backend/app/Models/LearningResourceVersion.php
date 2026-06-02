<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningResourceVersion extends Model
{
    protected $fillable = [
        'learning_resource_id',
        'version_number',
        'storage_disk',
        'file_path',
        'previous_file_path',
        'original_filename',
        'mime_type',
        'file_size',
        'preview_metadata',
        'change_notes',
        'uploaded_by',
        'uploaded_at',
    ];

    protected $hidden = [
        'storage_disk',
        'file_path',
        'previous_file_path',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'preview_metadata' => 'array',
            'uploaded_at' => 'datetime',
        ];
    }

    /**
     * Get the learning resource inverse relationship for this learning resource version.
     *
     * This admin/internal relationship resolves one LearningResource model.
     */
    public function learningResource(): BelongsTo
    {
        return $this->belongsTo(LearningResource::class);
    }

    /**
     * Get the uploaded by inverse relationship for this learning resource version.
     *
     * This admin/internal relationship resolves one User model.
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
