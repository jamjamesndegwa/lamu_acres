<?php

namespace App\Notifications;

use App\Models\EnquiryResponse;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EnquiryResponseNotification extends Notification
{
    use Queueable;

    public function __construct(public EnquiryResponse $response) {}

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
        $this->response->loadMissing('enquiry.property');

        return [
            'enquiry_id' => $this->response->enquiry_id,
            'title' => 'Response to your enquiry',
            'message' => $this->response->enquiry->property->title,
            'url' => route('buyer.enquiries.show', $this->response->enquiry),
        ];
    }
}
