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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CampaignDesk
{
    /**
     * @return list<string>
     */
    public function excludedCampaignRoleNames(): array
    {
        /** @var list<string> $roles */
        $roles = config('communications.campaigns.excluded_roles', ['staff', 'admin', 'super_admin']);

        return $roles;
    }

    /**
     * @return list<string>
     */
    public function marketablePartnerRoleNames(): array
    {
        /** @var list<string> $roles */
        $roles = config('communications.campaigns.marketable_roles', ['buyer', 'seller', 'broker']);

        return $roles;
    }

    /**
     * @return Builder<User>
     */
    public function marketableAudienceQuery(?string $search = null): Builder
    {
        $query = User::query()
            ->whereNull('suspended_at')
            ->whereDoesntHave('roles', function (Builder $query): void {
                $query->whereIn('name', $this->excludedCampaignRoleNames());
            })
            ->whereHas('roles', function (Builder $query): void {
                $query->whereIn('name', $this->marketablePartnerRoleNames());
            })
            ->orderBy('name')
            ->orderBy('id');

        if (filled($search)) {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            });
        }

        return $query;
    }

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

    /**
     * @param  list<int>  $userIds
     */
    public function syncRecipients(MessageCampaign $campaign, array $userIds): MessageCampaign
    {
        if ($campaign->status !== MessageCampaignStatus::Draft) {
            throw ValidationException::withMessages([
                'recipient_ids' => 'Recipients can only be changed while the campaign is a draft.',
            ]);
        }

        $userIds = array_values(array_unique(array_map(intval(...), $userIds)));

        if ($userIds === []) {
            throw ValidationException::withMessages([
                'recipient_ids' => 'Choose at least one recipient.',
            ]);
        }

        $eligibleIds = $this->eligibleRecipientQuery($campaign)
            ->whereIn('users.id', $userIds)
            ->pluck('users.id')
            ->all();

        if (count($eligibleIds) !== count($userIds)) {
            throw ValidationException::withMessages([
                'recipient_ids' => 'One or more selected accounts cannot receive this campaign on the chosen channel.',
            ]);
        }

        $campaign->recipients()->sync($eligibleIds);

        return $campaign->refresh();
    }

    public function queue(MessageCampaign $campaign): MessageCampaign
    {
        if ($campaign->status !== MessageCampaignStatus::Draft) {
            throw ValidationException::withMessages([
                'campaign' => 'Only draft campaigns can be queued.',
            ]);
        }

        if ($campaign->recipients()->count() === 0) {
            throw ValidationException::withMessages([
                'recipient_ids' => 'Choose at least one recipient before queuing delivery.',
            ]);
        }

        $recipients = $this->recipientQuery($campaign)->get();
        $campaign->forceFill([
            'status' => MessageCampaignStatus::Queued,
            'recipient_count' => $recipients->count(),
            'queued_at' => now(),
        ])->save();

        DB::afterCommit(fn () => DispatchMessageCampaign::dispatch($campaign->id)
            ->onQueue((string) config('communications.queues.campaigns')));

        return $campaign->refresh();
    }

    /**
     * @return array{eligible: bool, reasons: list<string>}
     */
    public function recipientEligibility(User $user, MessageChannel $channel): array
    {
        $reasons = [];

        if ($user->suspended_at !== null) {
            $reasons[] = 'Account suspended';
        }

        if ($channel === MessageChannel::Email) {
            if (blank($user->email)) {
                $reasons[] = 'No email address';
            }
            if (! $user->notify_by_email) {
                $reasons[] = 'Email notifications turned off';
            }
            if (! $user->marketing_email_consent) {
                $reasons[] = 'Marketing email consent not given';
            }
        }

        if ($channel === MessageChannel::Sms) {
            if (blank($user->phone)) {
                $reasons[] = 'No phone number';
            }
            if (! $user->marketing_sms_consent) {
                $reasons[] = 'Marketing SMS consent not given';
            }
        }

        return ['eligible' => $reasons === [], 'reasons' => $reasons];
    }

    /**
     * @return Collection<int, array{id: int, name: string, email: ?string, phone: ?string, eligible: bool, reasons: list<string>, partner_roles: list<string>}>
     */
    public function audienceForSelection(MessageChannel $channel, ?string $search = null): Collection
    {
        return $this->marketableAudienceQuery($search)
            ->with(['roles:id,name'])
            ->limit(500)
            ->get(['id', 'name', 'email', 'phone', 'notify_by_email', 'marketing_email_consent', 'marketing_sms_consent', 'suspended_at'])
            ->map(function (User $user) use ($channel): array {
                $check = $this->recipientEligibility($user, $channel);
                $partnerRoles = $user->roles
                    ->whereIn('name', $this->marketablePartnerRoleNames())
                    ->pluck('name')
                    ->values()
                    ->all();

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'eligible' => $check['eligible'],
                    'reasons' => $check['reasons'],
                    'partner_roles' => $partnerRoles,
                ];
            })
            ->filter(fn (array $row): bool => $row['eligible'])
            ->values();
    }

    /**
     * Accounts staff may add to a draft campaign for the chosen channel.
     *
     * @return Builder<User>
     */
    public function eligibleRecipientQuery(MessageCampaign $campaign): Builder
    {
        $query = $this->marketableAudienceQuery();

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

    /**
     * Selected recipients that remain eligible when the campaign is sent.
     *
     * @return Builder<User>
     */
    public function recipientQuery(MessageCampaign $campaign): Builder
    {
        return $this->eligibleRecipientQuery($campaign)
            ->whereIn(
                'users.id',
                DB::table('message_campaign_recipients')
                    ->select('user_id')
                    ->where('message_campaign_id', $campaign->id),
            );
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
