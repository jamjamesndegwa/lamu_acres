<?php

namespace App\Listeners;

use App\Events\EnquiryResponded;
use App\Notifications\EnquiryResponseNotification;

class NotifyEnquiryBuyer
{
    public function handle(EnquiryResponded $event): void
    {
        if (! $event->response->visible_to_buyer) {
            return;
        }

        $event->response->enquiry->buyer?->notify(new EnquiryResponseNotification($event->response));
    }
}
