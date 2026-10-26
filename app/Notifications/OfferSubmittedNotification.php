<?php

namespace App\Notifications;

use App\Models\Offer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OfferSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(public Offer $offer) {}

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
        $this->offer->loadMissing(['property', 'buyer']);

        return [
            'offer_id' => $this->offer->id,
            'property_id' => $this->offer->property_id,
            'title' => 'New offer on a listing',
            'message' => $this->offer->buyer->name.' offered KSh '.number_format($this->offer->amount).' on '.$this->offer->property->title,
            'url' => route('staff.offers.show', $this->offer, false),
        ];
    }
}
