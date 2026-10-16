<?php

namespace App\Notifications;

use App\Models\SalesTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SaleCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(public SalesTransaction $transaction) {}

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
            'title' => 'Sale completed',
            'message' => $this->transaction->reference.' has completed. Commission earning is a separate step.',
            'url' => $notifiable->hasPermission('workspace.broker') ? route('broker.deals.index')
                : ($notifiable->hasPermission('workspace.seller') ? $this->transaction->property->submitterUrl()
                    : ($this->transaction->enquiry_id ? route('buyer.enquiries.show', $this->transaction->enquiry_id) : route('buyer.matches.index'))),
        ];
    }
}
