<?php

namespace App\Notifications;

use App\Models\CommissionAgreement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CommissionSettlementNotification extends Notification
{
    use Queueable;

    public function __construct(public CommissionAgreement $agreement) {}

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
        return [
            'title' => 'Commission paid',
            'message' => $this->agreement->reference.' has been paid.',
            'url' => route('broker.agreements.show', $this->agreement),
        ];
    }
}
