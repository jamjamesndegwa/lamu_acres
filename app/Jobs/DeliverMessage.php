<?php

namespace App\Jobs;

use App\Contracts\Communications\SmsSender;
use App\Enums\MessageChannel;
use App\Models\MessageDelivery;
use App\Notifications\TemplatedDeskNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use Throwable;

class DeliverMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [15, 60];
    }

    public function __construct(public string $idempotencyKey, public ?Notification $notification = null) {}

    public function handle(SmsSender $sms): void
    {
        $delivery = MessageDelivery::query()->where('idempotency_key', $this->idempotencyKey)->first();

        if ($delivery === null || $delivery->status === 'sent' || $delivery->status === 'skipped') {
            return;
        }

        try {
            $this->deliver($delivery, $sms);
            $delivery->forceFill([
                'status' => 'sent',
                'attempts' => $this->attempts(),
                'sent_at' => now(),
                'error' => null,
            ])->save();
        } catch (Throwable $exception) {
            $delivery->forceFill([
                'status' => 'failed',
                'attempts' => $this->attempts(),
                'error' => class_basename($exception).': delivery failed.',
            ])->save();

            throw $exception;
        }
    }

    private function deliver(MessageDelivery $delivery, SmsSender $sms): void
    {
        $user = $delivery->recipient;

        if ($user === null) {
            return;
        }

        match ($delivery->channel) {
            MessageChannel::InApp => $this->inApp($delivery),
            MessageChannel::Email => Mail::raw($delivery->body, function ($message) use ($user, $delivery): void {
                $message->to($user->email)->subject((string) $delivery->subject);
            }),
            MessageChannel::Sms => $sms->send((string) $delivery->destination, $delivery->body),
            MessageChannel::Whatsapp => $this->whatsappLink($delivery),
        };
    }

    private function inApp(MessageDelivery $delivery): void
    {
        $id = $this->notificationId($delivery->idempotency_key);
        if ($delivery->recipient->notifications()->whereKey($id)->exists()) {
            return;
        }
        $notification = $this->notification ?? new TemplatedDeskNotification((string) $delivery->subject, $delivery->body, $delivery->destination);
        $notification->id = $id;
        $delivery->recipient->notifyNow($notification, ['database']);
    }

    private function whatsappLink(MessageDelivery $delivery): void
    {
        $key = substr($delivery->idempotency_key, 0, -strlen(MessageChannel::Whatsapp->value)).MessageChannel::InApp->value;
        $notice = $delivery->recipient->notifications()->whereKey($this->notificationId($key))->first();
        if ($notice !== null) {
            $data = $notice->data;
            if (! str_contains((string) ($data['message'] ?? ''), (string) $delivery->destination)) {
                $data['message'] = trim(($data['message'] ?? '').' '.$delivery->destination);
                $notice->update(['data' => $data]);
            }

            return;
        }
        $this->notification = new TemplatedDeskNotification((string) $delivery->subject, trim($delivery->body.' '.$delivery->destination));
        $this->inApp($delivery);
    }

    private function notificationId(string $key): string
    {
        $id = hash('md5', $key);

        return substr($id, 0, 8).'-'.substr($id, 8, 4).'-'.substr($id, 12, 4).'-'.substr($id, 16, 4).'-'.substr($id, 20);
    }
}
