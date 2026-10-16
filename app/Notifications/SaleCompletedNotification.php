<?php

namespace App\Notifications;

use App\Models\SalesTransaction;
use App\Models\User;
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
            'url' => $this->destination($notifiable),
        ];
    }

    private function destination(User $user): string
    {
        if ($user->hasPermission('workspace.broker')) {
            $introduction = $this->transaction->introduction;
            if ($introduction !== null && (int) $introduction->broker_id === (int) $user->id && in_array($introduction->status->value, ['accepted', 'active'], true)) {
                return route('broker.deals.show', $this->transaction);
            }
            $agreement = $this->transaction->agreements()->where('broker_id', $user->id)->whereIn('status', ['accepted', 'earned', 'payable', 'paid', 'disputed'])->first();

            return $agreement !== null ? route('broker.agreements.show', $agreement) : route('broker.agreements.index');
        }
        if ($user->hasPermission('workspace.seller')) {
            return (int) $this->transaction->property->submitted_by === (int) $user->id
                ? $this->transaction->property->submitterUrl() : route('seller.properties.index');
        }

        return $this->transaction->enquiry_id ? route('buyer.enquiries.show', $this->transaction->enquiry_id) : route('buyer.matches.index');
    }
}
