<?php

return [
    'expires_minutes' => (int) env('OTP_EXPIRES_MINUTES', 10),
    'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
    'resend_after_seconds' => max(1, (int) env('OTP_RESEND_AFTER_SECONDS', 15)),
];
