<?php

namespace App\Jobs;

use App\Contracts\Communications\SmsSender;
use App\Data\Communications\SmsSendResult;
use App\Enums\MessageChannel;
use App\Exceptions\Communications\SmsProviderException;
use App\Exceptions\Communications\SmsUncertainDeliveryException;
use App\Mail\DeskMessageMail;
use App\Models\MessageDelivery;
use App\Models\User;
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
            $result = $this->deliver($delivery, $sms);
            $delivery->forceFill([
                'status' => 'sent',
                'attempts' => $this->attempts(),
                'sent_at' => now(),
                'error' => null,
                'provider_reference' => $result?->messageId,
                'provider_meta' => $result?->meta(),
            ])->save();
        } catch (SmsUncertainDeliveryException|SmsProviderException $exception) {
            $delivery->forceFill([
                'status' => 'failed',
                'attempts' => $this->attempts(),
                'error' => class_basename($exception).': delivery failed.',
            ])->save();

            throw $exception;
        } catch (Throwable $exception) {
            $delivery->forceFill([
                'status' => 'failed',
                'attempts' => $this->attempts(),
                'error' => class_basename($exception).': delivery failed.',
            ])->save();

            throw $exception;
        }
    }

    private function deliver(MessageDelivery $delivery, SmsSender $sms): ?SmsSendResult
    {
        $user = $delivery->recipient;

        if ($user === null) {
            return null;
        }

        return match ($delivery->channel) {
            MessageChannel::InApp => $this->inApp($delivery),
            MessageChannel::Email => $this->email($user, $delivery),
            MessageChannel::Sms => $sms->send((string) $delivery->destination, $delivery->body),
            MessageChannel::Whatsapp => $this->whatsappLink($delivery),
        };
    }

    private function email(User $user, MessageDelivery $delivery): ?string
    {
        Mail::to($user->email)->send(new DeskMessageMail((string) $delivery->subject, $delivery->body));

        return null;
    }

    private function inApp(MessageDelivery $delivery): ?string
    {
        $id = $this->notificationId($delivery->idempotency_key);
        if ($delivery->recipient->notifications()->whereKey($id)->exists()) {
            return null;
        }
        $notification = $this->notification ?? new TemplatedDeskNotification((string) $delivery->subject, $delivery->body, $delivery->destination);
        $notification->id = $id;
        $delivery->recipient->notifyNow($notification, ['database']);

        return null;
    }

    private function whatsappLink(MessageDelivery $delivery): ?string
    {
        $key = substr($delivery->idempotency_key, 0, -strlen(MessageChannel::Whatsapp->value)).MessageChannel::InApp->value;
        $notice = $delivery->recipient->notifications()->whereKey($this->notificationId($key))->first();
        if ($notice !== null) {
            $data = $notice->data;
            if (! str_contains((string) ($data['message'] ?? ''), (string) $delivery->destination)) {
                $data['message'] = trim(($data['message'] ?? '').' '.$delivery->destination);
                $notice->update(['data' => $data]);
            }

            return null;
        }
        $this->notification = new TemplatedDeskNotification((string) $delivery->subject, trim($delivery->body.' '.$delivery->destination));
        $this->inApp($delivery);

        return null;
    }

    private function notificationId(string $key): string
    {
        $id = hash('md5', $key);

        return substr($id, 0, 8).'-'.substr($id, 8, 4).'-'.substr($id, 12, 4).'-'.substr($id, 16, 4).'-'.substr($id, 20);
    }
}
