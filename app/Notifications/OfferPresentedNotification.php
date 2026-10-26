<?php

namespace App\Notifications;

use App\Models\OfferPresentation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OfferPresentedNotification extends Notification
{
    use Queueable;

    public function __construct(public OfferPresentation $presentation) {}

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
        $this->presentation->loadMissing(['property', 'offer']);

        $acreage = $this->presentation->offer?->requested_acreage;

        return [
            'offer_id' => $this->presentation->offer_id,
            'offer_presentation_id' => $this->presentation->id,
            'property_id' => $this->presentation->property_id,
            'title' => 'Offer presented for your review',
            'message' => 'KSh '.number_format((int) $this->presentation->amount)
                .($acreage !== null ? ' for '.rtrim(rtrim(number_format((float) $acreage, 4, '.', ''), '0'), '.').' acres' : '')
                .' on '.$this->presentation->property->title,
            'url' => route('seller.offers.index', [], false),
        ];
    }
}
