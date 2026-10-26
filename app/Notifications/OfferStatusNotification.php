<?php

namespace App\Notifications;

use App\Models\Offer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OfferStatusNotification extends Notification
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
        $this->offer->loadMissing('property');

        $isBuyer = (int) $notifiable->id === (int) $this->offer->buyer_id;

        return [
            'offer_id' => $this->offer->id,
            'title' => 'Offer '.$this->offer->status->label(),
            'message' => $this->offer->property->title,
            'url' => $isBuyer
                ? route('buyer.offers.index', ['record' => $this->offer->id], false).'#offer-'.$this->offer->id
                : route('staff.offers.show', $this->offer, false),
        ];
    }
}
