<?php

namespace App\Jobs;

use App\Contracts\Communications\SmsSender;
use App\Enums\MessageChannel;
use App\Models\MessageDelivery;
use App\Notifications\TemplatedDeskNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Notifications\Notification;
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
                'error' => class_basename($exception).': delivery failed; retry from the communications desk.',
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
            MessageChannel::Whatsapp => $user->notify(new TemplatedDeskNotification(
                (string) $delivery->subject,
                trim($delivery->body.' '.$delivery->destination),
                null,
            )),
        };
    }

    private function inApp(MessageDelivery $delivery): void
    {
        $id = hash('md5', $delivery->idempotency_key);
        $id = substr($id, 0, 8).'-'.substr($id, 8, 4).'-'.substr($id, 12, 4).'-'.substr($id, 16, 4).'-'.substr($id, 20);
        if ($delivery->recipient->notifications()->whereKey($id)->exists()) {
            return;
        }
        $notification = $this->notification ?? new TemplatedDeskNotification((string) $delivery->subject, $delivery->body, $delivery->destination);
        $notification->id = $id;
        $delivery->recipient->notifyNow($notification, ['database']);
    }
}
