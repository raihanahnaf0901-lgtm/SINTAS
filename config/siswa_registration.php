<?php

return [
    'code_expires_minutes' => (int) env('SISWA_REGISTRATION_CODE_EXPIRES_MINUTES', 10),
    'code_max_attempts' => (int) env('SISWA_REGISTRATION_CODE_MAX_ATTEMPTS', 5),
    'send_max_attempts' => (int) env('SISWA_REGISTRATION_SEND_MAX_ATTEMPTS', 3),
    'send_decay_seconds' => (int) env('SISWA_REGISTRATION_SEND_DECAY_SECONDS', 60),
    'verify_max_attempts' => (int) env('SISWA_REGISTRATION_VERIFY_MAX_ATTEMPTS', 10),
    'verify_decay_seconds' => (int) env('SISWA_REGISTRATION_VERIFY_DECAY_SECONDS', 600),
];
