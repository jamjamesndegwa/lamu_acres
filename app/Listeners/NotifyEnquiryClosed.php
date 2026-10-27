<?php

namespace App\Listeners;

use App\Events\EnquiryClosed;
use App\Services\Communications\BuyerInterestNotifier;

class NotifyEnquiryClosed
{
    public function handle(EnquiryClosed $event): void
    {
        app(BuyerInterestNotifier::class)->enquiryClosed($event->enquiry);
    }
}
