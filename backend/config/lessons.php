<?php

return [
    'join_window' => [
        'lead_minutes' => (int) env('LESSON_JOIN_LEAD_MINUTES', 15),
        'grace_minutes' => (int) env('LESSON_JOIN_GRACE_MINUTES', 15),
    ],

    'booking_locks' => [
        'store' => env('LESSON_BOOKING_LOCK_STORE', 'redis'),
        'ttl_seconds' => (int) env('LESSON_BOOKING_LOCK_TTL_SECONDS', 60),
    ],
];
