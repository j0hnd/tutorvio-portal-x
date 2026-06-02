<?php

namespace App\Services;

use App\Models\LearningResource;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

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
            'original_filename' => $this->safeOriginalFilename($file),
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
        $disk = (string) config('learning_resources.disk', config('filesystems.default'));

        if (in_array($disk, config('learning_resources.disallowed_storage_disks', ['public']), true)) {
            throw new RuntimeException('Learning resource uploads must use protected storage.');
        }

        return $disk;
    }

    private function directory(): string
    {
        return trim((string) config('learning_resources.directory', 'learning-resources'), '/');
    }

    private function safeOriginalFilename(UploadedFile $file): string
    {
        $filename = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $filename = Str::ascii($filename);
        $filename = preg_replace('/[\x00-\x1F\x7F"\\\\\/]+/', '_', $filename) ?: 'upload';
        $filename = trim($filename, " .\t\n\r\0\x0B");

        return $filename !== '' ? $filename : 'upload';
    }
}
