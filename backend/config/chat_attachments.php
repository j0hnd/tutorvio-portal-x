<?php

return [
    'disk' => env('CHAT_ATTACHMENT_DISK', env('FILESYSTEM_DISK', 'local')),

    'directory' => env('CHAT_ATTACHMENT_DIRECTORY', 'chat-attachments'),

    'max_upload_kilobytes' => (int) env('CHAT_ATTACHMENT_MAX_UPLOAD_KB', 10240),

    'max_files_per_message' => (int) env('CHAT_ATTACHMENT_MAX_FILES_PER_MESSAGE', 5),

    'max_links_per_message' => (int) env('CHAT_ATTACHMENT_MAX_LINKS_PER_MESSAGE', 20),

    'allowed_mime_types' => [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ],

    'allowed_extensions' => [
        'pdf',
        'jpg',
        'jpeg',
        'png',
        'webp',
        'gif',
    ],

    'disallowed_storage_disks' => [
        'public',
    ],
];
