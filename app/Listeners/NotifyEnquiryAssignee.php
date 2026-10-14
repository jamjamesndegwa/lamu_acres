<?php

namespace App\Listeners;

use App\Events\EnquiryAssigned;
use App\Notifications\EnquiryAssignedNotification;

class NotifyEnquiryAssignee
{
    public function handle(EnquiryAssigned $event): void
    {
        $event->enquiry->assignee?->notify(new EnquiryAssignedNotification($event->enquiry));
    }
}
