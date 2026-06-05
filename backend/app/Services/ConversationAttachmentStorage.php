<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ConversationAttachmentStorage
{
    /**
     * @return array{storage_disk: string, file_path: string, original_filename: string, mime_type: string|null, file_size: int}
     */
    public function store(UploadedFile $file): array
    {
        $disk = $this->disk();
        $path = Storage::disk($disk)->putFile($this->directory(), $file);

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Chat attachment upload could not be stored.');
        }

        return [
            'storage_disk' => $disk,
            'file_path' => $path,
            'original_filename' => $this->safeOriginalFilename($file),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
        ];
    }

    private function disk(): string
    {
        $disk = (string) config('chat_attachments.disk', config('filesystems.default'));

        if (in_array($disk, config('chat_attachments.disallowed_storage_disks', ['public']), true)) {
            throw new RuntimeException('Chat attachment uploads must use protected storage.');
        }

        return $disk;
    }

    private function directory(): string
    {
        return trim((string) config('chat_attachments.directory', 'chat-attachments'), '/');
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
