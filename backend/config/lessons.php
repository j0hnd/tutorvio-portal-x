<?php

return [
    'join_window' => [
        'lead_minutes' => (int) env('LESSON_JOIN_LEAD_MINUTES', 15),
        'grace_minutes' => (int) env('LESSON_JOIN_GRACE_MINUTES', 15),
    ],
];
