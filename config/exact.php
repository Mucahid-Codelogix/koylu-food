<?php

return [

    'client_id' => env('EXACT_CLIENT_ID'),

    'client_secret' => env('EXACT_CLIENT_SECRET'),

    'redirect_uri' => env('EXACT_REDIRECT_URI') ?: rtrim((string) env('APP_URL', 'http://localhost'), '/').'/exact/oauth/callback',

    'division' => env('EXACT_DIVISION') ? (int) env('EXACT_DIVISION') : null,

    'base_url' => env('EXACT_BASE_URL', 'https://start.exactonline.nl'),

    'retry' => [
        'max_attempts' => 3,
        'initial_delay_seconds' => 1,
    ],

];
