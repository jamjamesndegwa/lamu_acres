<?php

namespace App\Notifications;

use App\Models\Enquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EnquiryAssignedNotification extends Notification
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
            'title' => 'Enquiry assigned to you',
            'message' => $this->enquiry->property->title,
            'url' => route('staff.enquiries.show', $this->enquiry),
        ];
    }
}
