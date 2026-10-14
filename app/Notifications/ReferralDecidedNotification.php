<?php

namespace App\Notifications;

use App\Models\BrokerIntroduction;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReferralDecidedNotification extends Notification
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
        $protected = $this->introduction->protectsCommission()
            ? 'Commission protection applies to this accepted referral.'
            : 'Commission protection does not apply until a referral is accepted.';

        return [
            'introduction_id' => $this->introduction->id,
            'title' => 'Referral '.$this->introduction->status->label(),
            'message' => $this->introduction->reference.'. '.$protected,
            'url' => route('broker.referrals.show', $this->introduction),
        ];
    }
}
