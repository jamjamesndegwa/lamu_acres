<?php

namespace App\Notifications;

use App\Models\PropertyInformationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PropertyInformationRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(public PropertyInformationRequest $request) {}

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
        $this->request->loadMissing('property');

        return [
            'information_request_id' => $this->request->id,
            'property_id' => $this->request->property_id,
            'title' => 'Staff asked for more information',
            'message' => 'Staff requested additional information about your listing. Open it to review the request.',
            'url' => $this->request->property->submitterUrl(),
        ];
    }
}
