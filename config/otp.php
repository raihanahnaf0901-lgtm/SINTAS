<?php

return [
    'expires_minutes' => (int) env('OTP_EXPIRES_MINUTES', 10),
    'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
    'send_limit' => 3,
    'send_decay_seconds' => 300,
];
