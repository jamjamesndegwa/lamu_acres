<?php

namespace App\Services\Communications;

use App\Contracts\Communications\SmsSender;
use App\Enums\MessageChannel;
use App\Enums\MessageEvent;
use App\Jobs\DeliverMessage;
use App\Models\MessageDelivery;
use App\Models\MessageTemplate;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class MessageDesk
{
    public function __construct(private SmsSender $sms) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function send(User $recipient, MessageEvent $event, array $context, string $subjectKey, ?Notification $inAppNotification = null): void
    {
        $context = $this->safeContext($context);
        $template = MessageTemplate::query()->where('event', $event)->first();
        $subject = $this->render($template->subject ?? $event->label(), $context);
        $body = $this->render($template->body ?? '{summary}', $context);

        foreach (MessageChannel::cases() as $channel) {
            if (! $this->allows($recipient, $channel)) {
                continue;
            }

            $destination = $this->destination($recipient, $channel, $context['url'] ?? null, $body);
            $key = $event->value.':'.$recipient->id.':'.$subjectKey.':'.$channel->value;

            if (MessageDelivery::query()->where('idempotency_key', $key)->exists()) {
                continue;
            }

            $status = 'queued';
            if ($channel === MessageChannel::Sms && ! $this->sms->configured()) {
                $status = 'skipped';
            }
            if ($destination === null) {
                $status = 'skipped';
            }

            try {
                $delivery = MessageDelivery::query()->create([
                    'idempotency_key' => $key,
                    'user_id' => $recipient->id,
                    'event' => $event,
                    'channel' => $channel,
                    'status' => $status,
                    'subject' => $subject,
                    'body' => $body,
                    'destination' => $destination,
                ]);
            } catch (QueryException) {
                continue;
            }

            if ($delivery->status === 'queued') {
                if (in_array($channel, [MessageChannel::Email, MessageChannel::Sms], true)) {
                    DB::afterCommit(function () use ($delivery): void {
                        try {
                            DeliverMessage::dispatch($delivery->idempotency_key);
                        } catch (\Throwable $exception) {
                            $delivery->refresh()->forceFill(['status' => 'failed', 'error' => class_basename($exception).': delivery failed.'])->save();
                        }
                    });

                    continue;
                }
                try {
                    DeliverMessage::dispatchSync($delivery->idempotency_key, $channel === MessageChannel::InApp ? $inAppNotification : null);
                } catch (\Throwable $exception) {
                    $delivery->refresh()->forceFill(['status' => 'failed', 'error' => class_basename($exception).': delivery failed.'])->save();
                }
            }
        }
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

    public function allows(User $user, MessageChannel $channel): bool
    {
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

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, string>
     */
    public function safeContext(array $context): array
    {
        $allowed = ['name', 'title', 'reference', 'status', 'summary', 'url'];
        $safe = [];

        foreach ($allowed as $key) {
            $safe[$key] = $this->scrub((string) ($context[$key] ?? ''));
        }

        return $safe;
    }

    public function render(string $template, array $context): string
    {
        $text = $template;

        foreach (['name', 'title', 'reference', 'status', 'summary', 'url'] as $key) {
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
