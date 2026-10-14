<?php

/*
| The Investor Pass list price stays on the subscription plan, in the plan
| currency (USD unless an administrator changes it). M-Pesa collects a
| separate whole-shilling KES amount that an administrator types in.
| This application does not convert currencies and does not keep an
| exchange rate. Access is not granted when a push is accepted.
*/

return [

    'driver' => env('MPESA_DRIVER', 'unconfigured'),

    'base_url' => env('MPESA_BASE_URL'),

    'consumer_key' => env('MPESA_CONSUMER_KEY'),

    'consumer_secret' => env('MPESA_CONSUMER_SECRET'),

    'passkey' => env('MPESA_PASSKEY'),

    'shortcode' => env('MPESA_SHORTCODE'),

    'callback_url' => env('MPESA_CALLBACK_URL'),

    'callback_secret' => env('MPESA_CALLBACK_SECRET'),

    'timeout_minutes' => (int) env('MPESA_TIMEOUT_MINUTES', 2),

];
