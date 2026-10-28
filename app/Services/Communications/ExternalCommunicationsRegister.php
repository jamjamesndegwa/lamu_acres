<?php

namespace App\Services\Communications;

use App\Enums\MessageChannel;
use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageEvent;
use App\Models\MessageDelivery;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ExternalCommunicationsRegister
{
    public const PERMISSION_EMAIL = 'communications.email.view';

    public const PERMISSION_SMS = 'communications.sms.view';

    /**
     * @return list<MessageChannel>
     */
    public function permittedChannels(User $viewer): array
    {
        $channels = [];

        if ($viewer->hasPermission(self::PERMISSION_EMAIL)) {
            $channels[] = MessageChannel::Email;
        }

        if ($viewer->hasPermission(self::PERMISSION_SMS)) {
            $channels[] = MessageChannel::Sms;
        }

        return $channels;
    }

    public function canAccessRegister(User $viewer): bool
    {
        return $this->permittedChannels($viewer) !== [];
    }

    public function canViewDelivery(User $viewer, MessageDelivery $delivery): bool
    {
        if (! $delivery->channel instanceof MessageChannel) {
            return false;
        }

        return in_array($delivery->channel, $this->permittedChannels($viewer), true);
    }

    /**
     * @return Builder<MessageDelivery>
     */
    public function query(User $viewer, Request $request): Builder
    {
        $channels = $this->permittedChannels($viewer);

        $query = MessageDelivery::query()
            ->with(['recipient', 'campaign'])
            ->whereIn('channel', array_map(fn (MessageChannel $channel): string => $channel->value, $channels))
            ->latest('created_at');

        if ($request->filled('channel')) {
            $channel = MessageChannel::tryFrom((string) $request->string('channel'));

            if ($channel !== null && in_array($channel, $channels, true)) {
                $query->where('channel', $channel);
            }
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->string('status'));
        }

        if ($request->filled('event')) {
            $event = MessageEvent::tryFrom((string) $request->string('event'));

            if ($event !== null) {
                $query->where('event', $event);
            }
        }

        if ($request->filled('kind')) {
            $kind = (string) $request->string('kind');

            if ($kind === 'campaign') {
                $query->whereNotNull('message_campaign_id');
            }

            if ($kind === 'transactional') {
                $query->whereNull('message_campaign_id')
                    ->whereNotIn('event', [
                        MessageEvent::PasswordResetRequested,
                        MessageEvent::EmailVerificationRequested,
                    ]);
            }

            if ($kind === 'security') {
                $query->whereIn('event', [
                    MessageEvent::PasswordResetRequested,
                    MessageEvent::EmailVerificationRequested,
                ]);
            }
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->date('to'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->integer('user_id'));
        }

        if ($request->filled('q')) {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim((string) $request->string('q'))).'%';
            $query->where(function (Builder $inner) use ($term): void {
                $inner->where('subject', 'like', $term)
                    ->orWhere('destination', 'like', $term)
                    ->orWhereHas('recipient', fn (Builder $recipient) => $recipient
                        ->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term));
            });
        }

        return $query;
    }

    /**
     * @return array<string, int>
     */
    public function summaryCounts(User $viewer, Request $request): array
    {
        $base = $this->query($viewer, $request->duplicate())->reorder();

        $counts = [
            'total' => (clone $base)->count(),
        ];

        foreach (MessageDeliveryStatus::summaryKeys() as $status) {
            $counts[$status] = (clone $base)->where('status', $status)->count();
        }

        $counts['submitted'] = $counts[MessageDeliveryStatus::Sent->value];

        return $counts;
    }

    public function previewBody(MessageDelivery $delivery, int $length = 120): string
    {
        $text = $this->displayBody($delivery);

        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $length - 1)).'…';
    }

    public function displayBody(MessageDelivery $delivery): string
    {
        if ($this->isSecurityEvent($delivery->event)) {
            return match ($delivery->event) {
                MessageEvent::PasswordResetRequested => 'Password reset instructions generated.',
                MessageEvent::EmailVerificationRequested => 'Email verification instructions generated.',
                default => 'Security-sensitive message generated.',
            };
        }

        return (string) $delivery->body;
    }

    public function isSecurityEvent(?MessageEvent $event): bool
    {
        return in_array($event, [MessageEvent::PasswordResetRequested, MessageEvent::EmailVerificationRequested], true);
    }

    public function relatedUrl(MessageDelivery $delivery): ?string
    {
        if ($delivery->message_campaign_id !== null) {
            return route('staff.messages.campaigns.show', $delivery->message_campaign_id);
        }

        return null;
    }

    public function stalledQueueWarning(): ?string
    {
        $stale = MessageDelivery::query()
            ->whereIn('channel', [MessageChannel::Email, MessageChannel::Sms])
            ->where('status', MessageDeliveryStatus::Queued->value)
            ->where('created_at', '<', now()->subMinutes(5))
            ->exists();

        if (! $stale) {
            return null;
        }

        $connection = (string) config('queue.default');

        if ($connection === 'database' && Schema::hasTable('jobs')) {
            $queues = array_values(array_unique(array_filter([
                config('communications.queues.communications'),
                config('communications.queues.critical'),
                config('communications.queues.campaigns'),
            ])));

            $pending = DB::table('jobs')->whereIn('queue', $queues)->count();

            if ($pending > 0) {
                return 'Queued — no active worker confirmed.';
            }
        }

        if ($connection === 'redis') {
            return 'Queued deliveries are waiting; worker activity is unverified.';
        }

        return 'Queued deliveries are waiting; worker activity is unverified.';
    }
}
