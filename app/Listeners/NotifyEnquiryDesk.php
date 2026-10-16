<?php

namespace App\Listeners;

use App\Enums\MessageEvent;
use App\Events\EnquirySubmitted;
use App\Models\User;
use App\Notifications\EnquirySubmittedNotification;
use App\Services\Communications\MessageDesk;

class NotifyEnquiryDesk
{
    public function handle(EnquirySubmitted $event): void
    {
        $staff = $event->enquiry->assignee?->can('respond', $event->enquiry)
            ? collect([$event->enquiry->assignee]) : User::permission('staff.buyers')->get();
        $event->enquiry->loadMissing('property');

        foreach ($staff as $user) {
            app(MessageDesk::class)->send($user, MessageEvent::BuyerEnquiry, [
                'name' => $user->name,
                'title' => $event->enquiry->property->title,
                'summary' => 'A buyer asked about '.$event->enquiry->property->title.'.',
                'url' => route('staff.enquiries.show', $event->enquiry),
            ], 'enquiry:'.$event->enquiry->id, new EnquirySubmittedNotification($event->enquiry));
        }
    }
}
