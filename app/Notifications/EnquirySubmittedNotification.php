<?php

namespace App\Notifications;

use App\Models\Enquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EnquirySubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(public Enquiry $enquiry) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $this->enquiry->loadMissing('property');

        return [
            'enquiry_id' => $this->enquiry->id,
            'title' => 'New property enquiry',
            'message' => $this->enquiry->property->title,
            'url' => route('staff.enquiries.show', $this->enquiry),
        ];
    }
}
