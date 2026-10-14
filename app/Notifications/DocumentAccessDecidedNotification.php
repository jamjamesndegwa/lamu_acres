<?php

namespace App\Notifications;

use App\Models\DocumentAccessRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DocumentAccessDecidedNotification extends Notification
{
    use Queueable;

    public function __construct(public DocumentAccessRequest $accessRequest, public string $message) {}

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
            'document_access_request_id' => $this->accessRequest->id,
            'title' => 'Document access',
            'message' => $this->message,
            'url' => route('buyer.matches.show', $this->accessRequest->property_match_id),
        ];
    }
}
