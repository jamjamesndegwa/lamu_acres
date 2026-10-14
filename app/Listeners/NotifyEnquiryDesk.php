<?php

namespace App\Listeners;

use App\Events\EnquirySubmitted;
use App\Models\User;
use App\Notifications\EnquirySubmittedNotification;
use Illuminate\Support\Facades\Notification;

class NotifyEnquiryDesk
{
    public function handle(EnquirySubmitted $event): void
    {
        $staff = User::permission('staff.buyers')->get();

        Notification::send($staff, new EnquirySubmittedNotification($event->enquiry));
    }
}
