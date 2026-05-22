<?php

return [
    'disk' => env('LEARNING_RESOURCE_DISK', env('FILESYSTEM_DISK', 'local')),

    'directory' => env('LEARNING_RESOURCE_DIRECTORY', 'learning-resources'),
];
