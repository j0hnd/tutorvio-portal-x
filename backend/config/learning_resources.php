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

    'allowed_extensions' => [
        'pdf',
        'doc',
        'docx',
        'xls',
        'xlsx',
        'csv',
        'ppt',
        'pptx',
        'odt',
        'ods',
        'odp',
    ],

    'disallowed_storage_disks' => [
        'public',
    ],

    'download' => [
        // auto: use temporary URLs for non-local disks that support them, stream otherwise
        // stream: always stream through the API response
        // temporary_url: always return an expiring temporary URL when supported
        'strategy' => env('LEARNING_RESOURCE_DOWNLOAD_STRATEGY', 'auto'),
        'temporary_url_ttl_minutes' => (int) env('LEARNING_RESOURCE_DOWNLOAD_TTL_MINUTES', 10),
    ],
];
