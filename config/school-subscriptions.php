<?php

return [
    'sandbox' => env('MIDTRANS_SANDBOX', true),
    'server_key' => env('MIDTRANS_SERVER_KEY'),

    'plans' => [
        'bulanan' => [
            'name' => 'Langganan sekolah bulanan',
            'amount' => 75000,
            'duration_months' => 1,
            'duration_days' => null,
        ],
    ],
];
