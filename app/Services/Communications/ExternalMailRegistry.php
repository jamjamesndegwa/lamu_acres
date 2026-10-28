<?php

namespace App\Services\Communications;

use App\Enums\MessageChannel;
use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageEvent;
use App\Models\MessageDelivery;
use App\Models\User;
use App\Notifications\AccountEmailChanged;
use App\Notifications\QueuedResetPassword;
use Illuminate\Database\QueryException;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\Notification;

class ExternalMailRegistry
{
    public function idempotencyKey(User $user, MessageEvent $event, string $suffix): string
    {
        return $event->value.':external:'.$user->id.':'.$suffix;
    }

    public function recordQueued(User $user, Notification $notification, string $subject, string $queueName): ?MessageDelivery
    {
        $event = $this->eventFor($notification);

        if ($event === null) {
            return null;
        }

        $suffix = $this->suffixFor($notification);
        $key = $this->idempotencyKey($user, $event, $suffix);

        if (MessageDelivery::query()->where('idempotency_key', $key)->exists()) {
            return MessageDelivery::query()->where('idempotency_key', $key)->first();
        }

        try {
            return MessageDelivery::query()->create([
                'idempotency_key' => $key,
                'user_id' => $user->id,
                'event' => $event,
                'channel' => MessageChannel::Email,
                'status' => MessageDeliveryStatus::Queued->value,
                'subject' => $subject,
                'body' => $this->safeBody($event),
                'destination' => $user->email,
                'queue_name' => $queueName,
                'queued_at' => now(),
            ]);
        } catch (QueryException) {
            return MessageDelivery::query()->where('idempotency_key', $key)->first();
        }
    }

    public function markProcessing(MessageDelivery $delivery): void
    {
        if ($delivery->status === MessageDeliveryStatus::Sent->value) {
            return;
        }

        $delivery->forceFill([
            'status' => MessageDeliveryStatus::Processing->value,
            'last_attempt_at' => now(),
            'attempts' => max(1, (int) $delivery->attempts),
        ])->save();
    }

    public function markSent(NotificationSent $event): void
    {
        $delivery = $this->findDelivery($event);

        if ($delivery === null) {
            return;
        }

        $delivery->forceFill([
            'status' => MessageDeliveryStatus::Sent->value,
            'sent_at' => now(),
            'last_attempt_at' => now(),
            'error' => null,
        ])->save();
    }

    public function markFailed(NotificationFailed $event): void
    {
        $delivery = $this->findDelivery($event);

        if ($delivery === null) {
            return;
        }

        $delivery->forceFill([
            'status' => MessageDeliveryStatus::Failed->value,
            'last_attempt_at' => now(),
            'attempts' => max(1, (int) $delivery->attempts + 1),
            'error' => 'Notification delivery failed.',
        ])->save();
    }

    public function findForNotification(User $user, Notification $notification): ?MessageDelivery
    {
        $event = $this->eventFor($notification);

        if ($event === null) {
            return null;
        }

        $key = $this->idempotencyKey($user, $event, $this->suffixFor($notification));

        return MessageDelivery::query()->where('idempotency_key', $key)->first();
    }

    private function findDelivery(NotificationSent|NotificationFailed $event): ?MessageDelivery
    {
        $notifiable = $event->notifiable;

        if (! $notifiable instanceof User) {
            return null;
        }

        $notificationEvent = $this->eventFor($event->notification);

        if ($notificationEvent === null) {
            return null;
        }

        $key = $this->idempotencyKey($notifiable, $notificationEvent, $this->suffixFor($event->notification));

        return MessageDelivery::query()->where('idempotency_key', $key)->first();
    }

    private function eventFor(Notification $notification): ?MessageEvent
    {
        return match (true) {
            $notification instanceof QueuedResetPassword => MessageEvent::PasswordResetRequested,
            $notification instanceof AccountEmailChanged => MessageEvent::EmailVerificationRequested,
            default => null,
        };
    }

    private function suffixFor(Notification $notification): string
    {
        if ($notification instanceof QueuedResetPassword) {
            return substr(hash('sha256', (string) $notification->token), 0, 16);
        }

        return substr(hash('sha256', $notification::class.':'.spl_object_id($notification)), 0, 16);
    }

    private function safeBody(MessageEvent $event): string
    {
        return match ($event) {
            MessageEvent::PasswordResetRequested => 'Password reset instructions generated.',
            MessageEvent::EmailVerificationRequested => 'Email verification instructions generated.',
            default => 'Security-sensitive message generated.',
        };
    }
}
