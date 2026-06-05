<?php

use App\Services\Search\MariaDbSearchService;

return [
    'driver' => env('SEARCH_DRIVER', 'mariadb'),

    'drivers' => [
        'mariadb' => MariaDbSearchService::class,
    ],
];
