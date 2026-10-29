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
    | Synchronous delivery (no queue worker)
    |--------------------------------------------------------------------------
    |
    | When true, transactional email/SMS from MessageDesk is sent in the HTTP
    | request via DeliverMessage::dispatchSync. Set COMMUNICATIONS_SYNC_DELIVERY=true
    | on hosts without Redis or a running queue:work process.
    |
    */

    'deliver_synchronously' => filter_var(env('COMMUNICATIONS_SYNC_DELIVERY', false), FILTER_VALIDATE_BOOL),

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
            fn (MessageEvent $event): bool => $event->isMandatory(),
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

    'campaigns' => [
        'excluded_roles' => ['staff', 'admin', 'super_admin'],
        'marketable_roles' => ['buyer', 'seller', 'broker'],
    ],

];
