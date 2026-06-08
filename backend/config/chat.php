<?php

return [
    'allow_teacher_message_pins' => filter_var(env('CHAT_ALLOW_TEACHER_MESSAGE_PINS', true), FILTER_VALIDATE_BOOLEAN),

    'allow_student_message_pins' => filter_var(env('CHAT_ALLOW_STUDENT_MESSAGE_PINS', false), FILTER_VALIDATE_BOOLEAN),

    'typing_indicator_ttl_seconds' => (int) env('CHAT_TYPING_INDICATOR_TTL_SECONDS', 10),
];
