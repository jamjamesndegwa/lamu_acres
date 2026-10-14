<?php

namespace App\Listeners;

use App\Enums\MessageEvent;
use App\Events\EnquirySubmitted;
use App\Models\User;
use App\Notifications\EnquirySubmittedNotification;
use App\Services\Communications\MessageDesk;
use Illuminate\Support\Facades\Notification;

class NotifyEnquiryDesk
{
    public function handle(EnquirySubmitted $event): void
    {
        $staff = User::permission('staff.buyers')->get();
        $event->enquiry->loadMissing('property');

        Notification::send($staff, new EnquirySubmittedNotification($event->enquiry));

        foreach ($staff as $user) {
            app(MessageDesk::class)->send($user, MessageEvent::BuyerEnquiry, [
                'name' => $user->name,
                'title' => $event->enquiry->property->title,
                'summary' => 'A buyer asked about '.$event->enquiry->property->title.'.',
                'url' => route('staff.enquiries.show', $event->enquiry),
            ], 'enquiry:'.$event->enquiry->id);
        }
    }
}
