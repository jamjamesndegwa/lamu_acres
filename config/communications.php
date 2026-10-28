<?php

use App\Enums\MessageEvent;

return [

    /*
    |--------------------------------------------------------------------------
    | Communications queue names
    |--------------------------------------------------------------------------
    |
    | Run dedicated workers per queue on production so critical mail is not
    | blocked by bulk campaigns. Example (Redis):
    |   php artisan queue:work redis --queue=critical,communications,campaigns
    |
    */

    'queues' => [
        'critical' => env('QUEUE_CRITICAL', 'critical'),
        'communications' => env('QUEUE_COMMUNICATIONS', 'communications'),
        'campaigns' => env('QUEUE_CAMPAIGNS', 'campaigns'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mandatory transactional events
    |--------------------------------------------------------------------------
    |
    | These bypass optional notify_by_email / SMS preference opt-outs.
    | Marketing broadcasts remain consent-gated via CampaignDesk.
    |
    */

    'mandatory_events' => array_map(
        fn (MessageEvent $event): string => $event->value,
        array_filter(
            MessageEvent::cases(),
            fn (MessageEvent $event): bool => $event !== MessageEvent::MarketingBroadcast,
        ),
    ),

    /*
    |--------------------------------------------------------------------------
    | Critical queue routing
    |--------------------------------------------------------------------------
    */

    'critical_events' => [
        MessageEvent::PasswordChanged->value,
        MessageEvent::PasswordResetRequested->value,
        MessageEvent::EmailVerificationRequested->value,
        MessageEvent::AccountSuspended->value,
    ],

    'visit_reminder_hours' => (int) env('VISIT_REMINDER_HOURS', 24),

    'subscription_expiry_reminder_days' => (int) env('SUBSCRIPTION_EXPIRY_REMINDER_DAYS', 7),

];
