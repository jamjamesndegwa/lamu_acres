<?php

namespace App\Listeners;

use App\Events\EnquiryResponded;
use App\Services\Communications\BuyerInterestNotifier;

class NotifyEnquiryBuyer
{
    public function handle(EnquiryResponded $event): void
    {
        app(BuyerInterestNotifier::class)->enquiryResponded($event->response);
    }
}
