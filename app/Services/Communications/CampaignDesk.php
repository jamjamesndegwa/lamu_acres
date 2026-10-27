<?php

namespace App\Services\Communications;

use App\Enums\MessageCampaignStatus;
use App\Enums\MessageChannel;
use App\Enums\MessageEvent;
use App\Jobs\DispatchMessageCampaign;
use App\Models\MessageCampaign;
use App\Models\MessageDelivery;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CampaignDesk
{
    public function create(User $author, MessageChannel $channel, string $subject, string $body): MessageCampaign
    {
        if (! in_array($channel, [MessageChannel::Sms, MessageChannel::Email], true)) {
            throw ValidationException::withMessages([
                'channel' => 'Bulk campaigns can only be sent by SMS or email.',
            ]);
        }

        return MessageCampaign::query()->create([
            'created_by' => $author->id,
            'channel' => $channel,
            'subject' => $subject,
            'body' => $body,
            'status' => MessageCampaignStatus::Draft,
        ]);
    }

    public function queue(MessageCampaign $campaign): MessageCampaign
    {
        if ($campaign->status !== MessageCampaignStatus::Draft) {
            throw ValidationException::withMessages([
                'campaign' => 'Only draft campaigns can be queued.',
            ]);
        }

        $recipients = $this->recipientQuery($campaign)->get();
        $campaign->forceFill([
            'status' => MessageCampaignStatus::Queued,
            'recipient_count' => $recipients->count(),
            'queued_at' => now(),
        ])->save();

        DB::afterCommit(fn () => DispatchMessageCampaign::dispatch($campaign->id));

        return $campaign->refresh();
    }

    /**
     * @return Builder<User>
     */
    public function recipientQuery(MessageCampaign $campaign): Builder
    {
        $query = User::query()
            ->whereNull('suspended_at')
            ->orderBy('id');

        return match ($campaign->channel) {
            MessageChannel::Email => $query
                ->where('marketing_email_consent', true)
                ->where('notify_by_email', true)
                ->whereNotNull('email')
                ->where('email', '!=', ''),
            MessageChannel::Sms => $query
                ->where('marketing_sms_consent', true)
                ->whereNotNull('phone')
                ->where('phone', '!=', ''),
            default => $query->whereRaw('0 = 1'),
        };
    }

    public function unsubscribeUrl(User $user): string
    {
        return route('communications.unsubscribe', $user->communications_unsubscribe_token);
    }

    public function appendComplianceFooter(User $user, string $body): string
    {
        $footer = 'Unsubscribe: '.$this->unsubscribeUrl($user);

        return trim($body."\n\n".$footer);
    }

    public function deliveryKey(MessageCampaign $campaign, User $user): string
    {
        return MessageEvent::MarketingBroadcast->value.':campaign:'.$campaign->id.':user:'.$user->id.':'.$campaign->channel->value;
    }

    public function recordDelivery(MessageCampaign $campaign, User $user, string $subject, string $body, ?string $destination): ?MessageDelivery
    {
        if ($destination === null || $destination === '') {
            return null;
        }

        if (MessageDelivery::query()->where('idempotency_key', $this->deliveryKey($campaign, $user))->exists()) {
            return MessageDelivery::query()->where('idempotency_key', $this->deliveryKey($campaign, $user))->first();
        }

        return MessageDelivery::query()->create([
            'idempotency_key' => $this->deliveryKey($campaign, $user),
            'user_id' => $user->id,
            'message_campaign_id' => $campaign->id,
            'event' => MessageEvent::MarketingBroadcast,
            'channel' => $campaign->channel,
            'status' => 'queued',
            'subject' => $subject,
            'body' => $body,
            'destination' => $destination,
        ]);
    }
}
