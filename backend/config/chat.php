<?php

return [
    'allow_teacher_message_pins' => filter_var(env('CHAT_ALLOW_TEACHER_MESSAGE_PINS', true), FILTER_VALIDATE_BOOLEAN),

    'allow_student_message_pins' => filter_var(env('CHAT_ALLOW_STUDENT_MESSAGE_PINS', false), FILTER_VALIDATE_BOOLEAN),

    'realtime' => [
        'enabled' => filter_var(env('CHAT_REALTIME_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

        'store' => env('CHAT_REALTIME_CACHE_STORE', env('CACHE_STORE', 'database')),

        'namespace' => env('CHAT_REALTIME_KEY_NAMESPACE', 'tvio:chat'),

        'environment' => env('CHAT_REALTIME_ENVIRONMENT', env('APP_ENV', 'production')),

        'ttl' => [
            'typing_seconds' => (int) env('CHAT_TYPING_TTL_SECONDS', env('CHAT_TYPING_INDICATOR_TTL_SECONDS', 10)),
            'presence_seconds' => (int) env('CHAT_PRESENCE_TTL_SECONDS', 60),
            'active_conversation_seconds' => (int) env('CHAT_ACTIVE_CONVERSATION_TTL_SECONDS', 120),
            'unread_count_seconds' => (int) env('CHAT_UNREAD_COUNT_TTL_SECONDS', 30),
            'duplicate_reminder_lock_seconds' => (int) env('CHAT_DUPLICATE_REMINDER_LOCK_TTL_SECONDS', 300),
            'delivery_status_seconds' => (int) env('CHAT_DELIVERY_STATUS_TTL_SECONDS', 86400),
            'rate_limit_seconds' => (int) env('CHAT_RATE_LIMIT_DECAY_SECONDS', 60),
        ],

        'rate_limits' => [
            'typing' => (int) env('CHAT_TYPING_RATE_LIMIT_MAX_ATTEMPTS', 30),
            'presence' => (int) env('CHAT_PRESENCE_RATE_LIMIT_MAX_ATTEMPTS', 60),
            'message_send' => (int) env('CHAT_MESSAGE_SEND_RATE_LIMIT_MAX_ATTEMPTS', 30),
            'reminder' => (int) env('CHAT_REMINDER_RATE_LIMIT_MAX_ATTEMPTS', 10),
        ],
    ],

    'typing_indicator_ttl_seconds' => (int) env('CHAT_TYPING_TTL_SECONDS', env('CHAT_TYPING_INDICATOR_TTL_SECONDS', 10)),
];
