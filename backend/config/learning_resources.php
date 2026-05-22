<?php

return [
    'disk' => env('LEARNING_RESOURCE_DISK', env('FILESYSTEM_DISK', 'local')),

    'directory' => env('LEARNING_RESOURCE_DIRECTORY', 'learning-resources'),

    'max_upload_kilobytes' => (int) env('LEARNING_RESOURCE_MAX_UPLOAD_KB', 10240),

    'allowed_mime_types' => [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/csv',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.oasis.opendocument.spreadsheet',
        'application/vnd.oasis.opendocument.presentation',
    ],
];
