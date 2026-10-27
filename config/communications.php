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

];
