<?php

/*
| The Investor Pass list price stays on the subscription plan, in the plan
| currency (USD unless an administrator changes it). M-Pesa collects a
| separate whole-shilling KES amount that an administrator types in.
| This application does not convert currencies and does not keep an
| exchange rate. Access is not granted when a push is accepted.
|
| Safaricom posts STK callbacks to MPESA_CALLBACK_URL. Include the callback
| secret as the final URL path segment (see payments.mpesa.callback route)
| because Daraja does not send a custom authentication header.
*/

return [

    'driver' => env('MPESA_DRIVER', 'unconfigured'),

    'environment' => env('MPESA_ENVIRONMENT', 'sandbox'),

    'sandbox_base_url' => env('MPESA_SANDBOX_BASE_URL', 'https://sandbox.safaricom.co.ke'),

    'production_base_url' => env('MPESA_PRODUCTION_BASE_URL', 'https://api.safaricom.co.ke'),

    'base_url' => env('MPESA_BASE_URL'),

    'consumer_key' => env('MPESA_CONSUMER_KEY'),

    'consumer_secret' => env('MPESA_CONSUMER_SECRET'),

    'passkey' => env('MPESA_PASSKEY'),

    'shortcode' => env('MPESA_SHORTCODE'),

    'transaction_type' => env('MPESA_TRANSACTION_TYPE', 'CustomerPayBillOnline'),

    'callback_url' => env('MPESA_CALLBACK_URL'),

    'callback_secret' => env('MPESA_CALLBACK_SECRET'),

    'timeout_minutes' => (int) env('MPESA_TIMEOUT_MINUTES', 2),

    'http_timeout_seconds' => (int) env('MPESA_HTTP_TIMEOUT', 20),

    'oauth_cache_seconds' => (int) env('MPESA_OAUTH_CACHE_SECONDS', 3300),

];
