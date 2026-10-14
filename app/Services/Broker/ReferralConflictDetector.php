<?php

namespace App\Services\Broker;

use App\Enums\BrokerIntroductionStatus;
use App\Models\BrokerIntroduction;
use App\Models\Enquiry;
use App\Models\User;
use Illuminate\Support\Str;

class ReferralConflictDetector
{
    /**
     * @param  array<int, int>  $propertyIds
     * @return array<int, array<string, mixed>>
     */
    public function detect(User $broker, array $propertyIds, string $email, ?string $phone): array
    {
        $email = Str::lower(trim($email));
        $buyer = User::query()->where('email', $email)->first();
        $flags = [];

        $existing = BrokerIntroduction::query()
            ->with('broker:id,name')
            ->whereNotIn('status', [
                BrokerIntroductionStatus::Rejected,
                BrokerIntroductionStatus::Withdrawn,
                BrokerIntroductionStatus::Expired,
            ])
            ->where(function ($query) use ($email, $phone, $buyer): void {
                $query->where('buyer_email', $email);

                if ($buyer) {
                    $query->orWhere('buyer_id', $buyer->id);
                }

                if (is_string($phone) && $phone !== '') {
                    $query->orWhere('buyer_phone', $phone);
                }
            })
            ->where(function ($query) use ($propertyIds): void {
                $query->whereIn('property_id', $propertyIds)
                    ->orWhereHas('properties', fn ($related) => $related->whereIn('properties.id', $propertyIds));
            })
            ->get();

        foreach ($existing as $introduction) {
            $flags[] = [
                'type' => $introduction->broker_id === $broker->id ? 'existing_combination' : 'prior_introduction',
                'introduction_id' => $introduction->id,
                'reference' => $introduction->reference,
                'broker_id' => $introduction->broker_id,
                'broker_name' => $introduction->broker?->name,
                'status' => $introduction->status->value,
            ];
        }

        if ($buyer) {
            $direct = Enquiry::query()
                ->where('buyer_id', $buyer->id)
                ->whereIn('property_id', $propertyIds)
                ->whereNull('broker_introduction_id')
                ->pluck('property_id');

            foreach ($direct as $propertyId) {
                $flags[] = [
                    'type' => 'direct_relationship',
                    'property_id' => $propertyId,
                    'buyer_id' => $buyer->id,
                ];
            }
        }

        return $flags;
    }

    /**
     * @param  array<int, array<string, mixed>>  $flags
     * @return array<int, string>
     */
    public function brokerMessages(array $flags): array
    {
        $messages = [];

        foreach ($flags as $flag) {
            $messages[] = match ($flag['type']) {
                'existing_combination' => 'This buyer and property were already introduced. The new referral is still submitted for staff review.',
                'prior_introduction' => 'Another introduction already covers this buyer and property. Staff will review the overlap. The other broker is not named here.',
                'direct_relationship' => 'This buyer already has a direct enquiry on one of these properties. Staff will decide whether the referral can be protected.',
                default => 'Staff need to review a possible referral conflict.',
            };
        }

        return array_values(array_unique($messages));
    }
}
