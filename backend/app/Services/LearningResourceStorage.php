<?php

namespace App\Services;

use App\Models\LearningResource;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class LearningResourceStorage
{
    /**
     * @return array{storage_disk: string, file_path: string, original_filename: string, mime_type: string|null, file_size: int}
     */
    public function store(UploadedFile $file): array
    {
        $disk = $this->disk();
        $path = Storage::disk($disk)->putFile($this->directory(), $file);

        return [
            'storage_disk' => $disk,
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
        ];
    }

    public function delete(LearningResource $resource): bool
    {
        if (! $resource->hasStoredFile()) {
            return true;
        }

        return Storage::disk($resource->storageDisk())->delete($resource->file_path);
    }

    private function disk(): string
    {
        return (string) config('learning_resources.disk', config('filesystems.default'));
    }

    private function directory(): string
    {
        return trim((string) config('learning_resources.directory', 'learning-resources'), '/');
    }
}
