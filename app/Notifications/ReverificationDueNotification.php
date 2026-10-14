<?php

namespace App\Notifications;

use App\Models\VerificationItem;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReverificationDueNotification extends Notification
{
    use Queueable;

    public function __construct(public VerificationItem $item) {}

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
            'verification_item_id' => $this->item->id,
            'title' => 'Reverification due',
            'message' => $this->item->category->label(),
            'url' => route('staff.verification.show', $this->item->property),
        ];
    }
}
