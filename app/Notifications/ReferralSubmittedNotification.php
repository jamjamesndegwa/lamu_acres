<?php

namespace App\Notifications;

use App\Models\BrokerIntroduction;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReferralSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(public BrokerIntroduction $introduction) {}

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
            'introduction_id' => $this->introduction->id,
            'title' => 'Buyer referral submitted',
            'message' => $this->introduction->reference,
            'url' => route('staff.referrals.show', $this->introduction),
        ];
    }
}
