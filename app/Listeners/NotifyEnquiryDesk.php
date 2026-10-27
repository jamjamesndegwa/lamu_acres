<?php

namespace App\Listeners;

use App\Events\EnquirySubmitted;
use App\Services\Communications\BuyerInterestNotifier;

class NotifyEnquiryDesk
{
    public function handle(EnquirySubmitted $event): void
    {
        app(BuyerInterestNotifier::class)->enquirySubmitted($event->enquiry);
    }
}
