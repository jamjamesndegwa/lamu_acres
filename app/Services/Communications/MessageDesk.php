<?php

namespace App\Services\Communications;

use App\Contracts\Communications\SmsSender;
use App\Enums\MessageChannel;
use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageEvent;
use App\Jobs\DeliverMessage;
use App\Models\MessageDelivery;
use App\Models\MessageTemplate;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Support\CommunicationsDelivery;
use Illuminate\Database\QueryException;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MessageDesk
{
    public function __construct(private SmsSender $sms) {}

    /**
     * @param  array<string, mixed>  $context
     * @param  list<MessageChannel>|null  $channels
     */
    public function send(User $recipient, MessageEvent $event, array $context, string $subjectKey, ?Notification $inAppNotification = null, ?array $channels = null): void
    {
        $context = $this->safeContext($context);
        $template = MessageTemplate::query()->where('event', $event)->first();
        $subject = $this->render($template->subject ?? $event->label(), $context);
        $body = $this->render($template->body ?? '{summary}', $context);

        foreach (MessageChannel::cases() as $channel) {
            if ($channels !== null && ! in_array($channel, $channels, true)) {
                continue;
            }
            if (! $this->allows($recipient, $channel, $event)) {
                continue;
            }

            $destination = $this->destination($recipient, $channel, $context['url'] ?? null, $body);
            $key = $event->value.':'.$recipient->id.':'.$subjectKey.':'.$channel->value;

            if (MessageDelivery::query()->where('idempotency_key', $key)->exists()) {
                continue;
            }

            $skipReason = null;

            if (in_array($channel, [MessageChannel::InApp, MessageChannel::Whatsapp], true)) {
                $status = MessageDeliveryStatus::Queued->value;
            } else {
                $status = MessageDeliveryStatus::Queued->value;

                if ($channel === MessageChannel::Sms && ! $this->sms->configured()) {
                    $status = MessageDeliveryStatus::Skipped->value;
                    $skipReason = 'policy: SMS provider not configured';
                }

                if ($channel === MessageChannel::Email && ! $this->emailDispatchReady()) {
                    $status = MessageDeliveryStatus::Skipped->value;
                    $skipReason = 'policy: SMTP not configured for delivery';
                }

                if ($destination === null) {
                    $status = MessageDeliveryStatus::Skipped->value;
                    $skipReason = 'policy: missing destination';
                }
            }

            $queueName = in_array($channel, [MessageChannel::Email, MessageChannel::Sms], true)
                ? $this->queueFor($event)
                : null;

            try {
                $delivery = MessageDelivery::query()->create([
                    'idempotency_key' => $key,
                    'user_id' => $recipient->id,
                    'event' => $event,
                    'channel' => $channel,
                    'status' => $status,
                    'queue_name' => $queueName,
                    'queued_at' => $status === MessageDeliveryStatus::Queued->value ? now() : null,
                    'subject' => $subject,
                    'body' => $body,
                    'destination' => $destination,
                    'error' => $skipReason ?? null,
                ]);
            } catch (QueryException) {
                continue;
            }

            if ($delivery->status === MessageDeliveryStatus::Queued->value) {
                if (in_array($channel, [MessageChannel::Email, MessageChannel::Sms], true)) {
                    $this->dispatchDeliverMessage($delivery, $event);

                    continue;
                }
                try {
                    DeliverMessage::dispatchSync($delivery->idempotency_key, $channel === MessageChannel::InApp ? $inAppNotification : null);
                } catch (\Throwable $exception) {
                    $delivery->refresh()->forceFill([
                        'status' => MessageDeliveryStatus::Failed->value,
                        'error' => class_basename($exception).': delivery failed.',
                    ])->save();
                }
            }
        }
    }

    /**
     * Admin/staff one-off email to a user, optionally to a different inbox than their account email.
     *
     * @throws ValidationException
     */
    public function sendStaffManualEmail(User $recipient, string $subject, string $body, ?string $destinationOverride = null): MessageDelivery
    {
        $subject = $this->scrub(trim($subject));
        $body = $this->scrub(trim($body));

        if ($subject === '' || $body === '') {
            throw ValidationException::withMessages([
                'subject' => 'Subject and message are required.',
            ]);
        }

        $destination = filled($destinationOverride)
            ? trim($destinationOverride)
            : ($recipient->email ?? '');

        if (! filter_var($destination, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'destination_email' => 'Provide a valid email address for this user.',
            ]);
        }

        if (! $this->emailDispatchReady()) {
            throw ValidationException::withMessages([
                'subject' => 'SMTP is not configured for outbound email.',
            ]);
        }

        $event = MessageEvent::StaffManualEmail;
        $key = $event->value.':'.$recipient->id.':manual:'.now()->format('YmdHis').':'.substr(hash('sha256', $subject.$body.$destination), 0, 12);

        $delivery = MessageDelivery::query()->create([
            'idempotency_key' => $key,
            'user_id' => $recipient->id,
            'event' => $event,
            'channel' => MessageChannel::Email,
            'status' => MessageDeliveryStatus::Queued->value,
            'queue_name' => $this->queueFor($event),
            'queued_at' => now(),
            'subject' => $subject,
            'body' => $body,
            'destination' => $destination,
        ]);

        $this->dispatchDeliverMessage($delivery, $event);

        return $delivery->refresh();
    }

    private function dispatchDeliverMessage(MessageDelivery $delivery, MessageEvent $event): void
    {
        $queueName = $this->queueFor($event);
        $key = $delivery->idempotency_key;

        if (CommunicationsDelivery::deliverSynchronously()) {
            try {
                DeliverMessage::dispatchSync($key);
            } catch (\Throwable $exception) {
                $delivery->refresh()->forceFill([
                    'status' => MessageDeliveryStatus::Failed->value,
                    'error' => class_basename($exception).': delivery failed.',
                ])->save();
            }

            return;
        }

        DB::afterCommit(function () use ($delivery, $key, $queueName): void {
            try {
                DeliverMessage::dispatch($key)
                    ->onQueue($queueName);
            } catch (\Throwable $exception) {
                $delivery->refresh()->forceFill([
                    'error' => 'Dispatch: '.class_basename($exception).': '.$exception->getMessage(),
                ])->save();
            }
        });
    }

    private function emailDispatchReady(): bool
    {
        $mailer = (string) config('mail.default');

        if (in_array($mailer, ['log', 'array'], true)) {
            return filled(config('mail.from.address'));
        }

        return (bool) (app(MailPreflight::class)->inspect()['smtp_ready'] ?? false);
    }

    /**
     * @param  array<string, bool>  $channels
     */
    public function savePreferences(User $user, array $channels): void
    {
        foreach ([MessageChannel::Sms, MessageChannel::Whatsapp] as $channel) {
            if (! array_key_exists($channel->value, $channels)) {
                continue;
            }

            NotificationPreference::query()->updateOrCreate(
                ['user_id' => $user->id, 'channel' => $channel],
                ['enabled' => (bool) $channels[$channel->value]],
            );
        }

        if (array_key_exists('marketing_email', $channels)) {
            $user->marketing_email_consent = (bool) $channels['marketing_email'];
        }

        if (array_key_exists('marketing_sms', $channels)) {
            $user->marketing_sms_consent = (bool) $channels['marketing_sms'];
        }
    }

    public function allows(User $user, MessageChannel $channel, MessageEvent $event): bool
    {
        if ($event->isMarketing()) {
            return match ($channel) {
                MessageChannel::Email => $user->marketing_email_consent && filled($user->email),
                MessageChannel::Sms => $user->marketing_sms_consent && filled($user->phone),
                default => false,
            };
        }

        if ($event->isMandatory()) {
            return match ($channel) {
                MessageChannel::InApp => true,
                MessageChannel::Email => filled($user->email),
                MessageChannel::Sms => $this->optionalChannelEnabled($user, $channel) && filled($user->phone),
                MessageChannel::Whatsapp => $this->optionalChannelEnabled($user, $channel),
            };
        }

        $preference = $user->notificationPreferences()->where('channel', $channel)->first();

        if ($preference !== null) {
            return $preference->enabled;
        }

        return match ($channel) {
            MessageChannel::InApp => true,
            MessageChannel::Email => (bool) $user->notify_by_email && filled($user->email),
            MessageChannel::Sms, MessageChannel::Whatsapp => false,
        };
    }

    private function optionalChannelEnabled(User $user, MessageChannel $channel): bool
    {
        $preference = $user->notificationPreferences()->where('channel', $channel)->first();

        if ($preference !== null) {
            return $preference->enabled;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, string>
     */
    public function safeContext(array $context): array
    {
        $allowed = ['name', 'title', 'reference', 'status', 'summary', 'url', 'watch'];
        $safe = [];

        foreach ($allowed as $key) {
            $safe[$key] = $this->scrub((string) ($context[$key] ?? ''));
        }

        return $safe;
    }

    public function render(string $template, array $context): string
    {
        $text = $template;

        foreach (['name', 'title', 'reference', 'status', 'summary', 'url', 'watch'] as $key) {
            $text = str_replace('{'.$key.'}', (string) ($context[$key] ?? ''), $text);
        }

        return $this->scrub($text);
    }

    private function destination(User $user, MessageChannel $channel, ?string $url, string $body): ?string
    {
        return match ($channel) {
            MessageChannel::InApp => filled($url) ? $url : url('/'),
            MessageChannel::Email => filled($user->email) ? $user->email : null,
            MessageChannel::Sms => $this->phone($user->phone),
            MessageChannel::Whatsapp => $this->whatsappLink($user->phone, $body),
        };
    }

    private function whatsappLink(?string $phone, string $body): ?string
    {
        $digits = $this->phone($phone);

        if ($digits === null) {
            return null;
        }

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($body);
    }

    private function phone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '254'.substr($digits, 1);
        }

        return $digits;
    }

    public function queueFor(MessageEvent $event): string
    {
        $critical = config('communications.critical_events', []);

        if (in_array($event->value, $critical, true)) {
            return (string) config('communications.queues.critical');
        }

        return (string) config('communications.queues.communications');
    }

    private function scrub(string $value): string
    {
        $value = preg_replace('/-?\d+\.\d{5,}/', '[location withheld]', $value) ?? $value;
        $value = preg_replace('#(?:evidence|ownership|broker-listings)/[^\s]+#', '[document withheld]', $value) ?? $value;

        foreach (['restricted_latitude', 'restricted_longitude', 'parcel_identifier', 'seller_contact'] as $secret) {
            $value = str_replace($secret, '', $value);
        }

        return trim($value);
    }
}
