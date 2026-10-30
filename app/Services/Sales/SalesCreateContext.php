<?php

namespace App\Services\Sales;

use App\Enums\OfferStatus;
use App\Enums\SalesOrigin;
use App\Models\BrokerIntroduction;
use App\Models\Enquiry;
use App\Models\Offer;
use App\Models\Property;
use App\Models\SalesTransaction;
use App\Models\User;

class SalesCreateContext
{
    /**
     * @return array{
     *     property: array<string, mixed>|null,
     *     sellers: array<int, array<string, mixed>>,
     *     sale_instructions: array<int, array<string, mixed>>,
     *     buyer_offers: array<int, array<string, mixed>>,
     *     property_offers: array<int, array<string, mixed>>,
     *     matching_offers: array<int, array<string, mixed>>,
     *     open_transactions: array<int, array<string, mixed>>,
     *     linkable_enquiries: array<int, array<string, mixed>>,
     *     linkable_introductions: array<int, array<string, mixed>>,
     *     alerts: array<int, string>,
     *     suggestions: array<string, mixed>,
     * }
     */
    public function assemble(?int $propertyId, ?int $buyerId, ?SalesOrigin $origin = null): array
    {
        $property = $propertyId !== null
            ? Property::query()->with(['owners.user:id,name', 'saleInstructions.seller:id,name'])->find($propertyId)
            : null;
        $buyer = $buyerId !== null ? User::query()->find($buyerId) : null;

        $activeStatuses = [
            OfferStatus::Submitted,
            OfferStatus::UnderReview,
            OfferStatus::Accepted,
        ];

        $sellers = $this->sellers($property);
        $saleInstructions = $this->saleInstructions($property);
        $buyerOffers = $this->offersForBuyer($buyer, $activeStatuses, $propertyId);
        $propertyOffers = $this->offersForProperty($property, $activeStatuses, $buyerId);
        $matchingOffers = $this->matchingOffers($propertyId, $buyerId, $activeStatuses);
        $openTransactions = $this->openTransactions($propertyId, $buyerId);
        $linkableEnquiries = $this->linkableEnquiries($propertyId, $buyerId);
        $linkableIntroductions = $this->linkableIntroductions($propertyId, $buyerId);

        $suggestions = $this->suggestions(
            $origin,
            $property,
            $matchingOffers,
            $linkableEnquiries,
            $linkableIntroductions,
            $saleInstructions,
        );

        $alerts = $this->alerts($openTransactions, $matchingOffers, $property, $buyer);

        return [
            'property' => $property !== null ? [
                'id' => $property->id,
                'title' => $property->title,
                'status' => $property->status->label(),
                'remaining_acreage' => $property->remainingAcreage(),
                'offered_acreage' => $property->offeredAcreage(),
            ] : null,
            'sellers' => $sellers,
            'sale_instructions' => $saleInstructions,
            'buyer_offers' => $buyerOffers,
            'property_offers' => $propertyOffers,
            'matching_offers' => $matchingOffers,
            'open_transactions' => $openTransactions,
            'linkable_enquiries' => $linkableEnquiries,
            'linkable_introductions' => $linkableIntroductions,
            'alerts' => $alerts,
            'suggestions' => $suggestions,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sellers(?Property $property): array
    {
        if ($property === null) {
            return [];
        }

        return $property->owners
            ->filter(fn ($owner) => $owner->user !== null)
            ->map(fn ($owner) => [
                'user_id' => $owner->user_id,
                'name' => $owner->user->name,
                'relationship' => $owner->relationship->label(),
                'status' => $owner->status->label(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function saleInstructions(?Property $property): array
    {
        if ($property === null) {
            return [];
        }

        return $property->saleInstructions
            ->take(5)
            ->map(fn ($instruction) => [
                'id' => $instruction->id,
                'seller' => $instruction->seller?->name,
                'kind' => $instruction->kind->label(),
                'excerpt' => str($instruction->body)->limit(80)->value(),
            ])
            ->all();
    }

    /**
     * @param  array<int, OfferStatus>  $activeStatuses
     * @return array<int, array<string, mixed>>
     */
    private function offersForBuyer(?User $buyer, array $activeStatuses, ?int $propertyId): array
    {
        if ($buyer === null) {
            return [];
        }

        return Offer::query()
            ->with(['property:id,title', 'salesTransaction:id,reference'])
            ->where('buyer_id', $buyer->id)
            ->whereIn('status', $activeStatuses)
            ->when($propertyId !== null, fn ($query) => $query->where('property_id', '!=', $propertyId))
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (Offer $offer) => $this->offerRow($offer))
            ->all();
    }

    /**
     * @param  array<int, OfferStatus>  $activeStatuses
     * @return array<int, array<string, mixed>>
     */
    private function offersForProperty(?Property $property, array $activeStatuses, ?int $buyerId): array
    {
        if ($property === null) {
            return [];
        }

        return Offer::query()
            ->with(['buyer:id,name', 'salesTransaction:id,reference'])
            ->where('property_id', $property->id)
            ->whereIn('status', $activeStatuses)
            ->when($buyerId !== null, fn ($query) => $query->where('buyer_id', '!=', $buyerId))
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (Offer $offer) => $this->offerRow($offer))
            ->all();
    }

    /**
     * @param  array<int, OfferStatus>  $activeStatuses
     * @return array<int, array<string, mixed>>
     */
    private function matchingOffers(?int $propertyId, ?int $buyerId, array $activeStatuses): array
    {
        if ($propertyId === null || $buyerId === null) {
            return [];
        }

        return Offer::query()
            ->with(['salesTransaction:id,reference'])
            ->where('property_id', $propertyId)
            ->where('buyer_id', $buyerId)
            ->whereIn('status', $activeStatuses)
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (Offer $offer) => $this->offerRow($offer))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function offerRow(Offer $offer): array
    {
        return [
            'id' => $offer->id,
            'amount' => $offer->amount,
            'status' => $offer->status->label(),
            'requested_acreage' => $offer->requested_acreage,
            'buyer_name' => $offer->relationLoaded('buyer') ? $offer->buyer?->name : null,
            'property_title' => $offer->relationLoaded('property') ? $offer->property?->title : null,
            'sales_case' => $offer->salesTransaction?->reference,
            'sales_transaction_id' => $offer->sales_transaction_id,
            'broker_introduction_id' => $offer->broker_introduction_id,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function openTransactions(?int $propertyId, ?int $buyerId): array
    {
        if ($propertyId === null && $buyerId === null) {
            return [];
        }

        return SalesTransaction::query()
            ->with(['property:id,title', 'buyer:id,name'])
            ->open()
            ->when($propertyId !== null, fn ($query) => $query->where('property_id', $propertyId))
            ->when($buyerId !== null, fn ($query) => $query->where('buyer_id', $buyerId))
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (SalesTransaction $transaction) => [
                'id' => $transaction->id,
                'reference' => $transaction->reference,
                'stage' => $transaction->stage->label(),
                'property' => $transaction->property?->title,
                'buyer' => $transaction->buyer?->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function linkableEnquiries(?int $propertyId, ?int $buyerId): array
    {
        if ($propertyId === null && $buyerId === null) {
            return [];
        }

        return Enquiry::query()
            ->with('property:id,title')
            ->whereDoesntHave('salesTransaction')
            ->when($propertyId !== null, fn ($query) => $query->where('property_id', $propertyId))
            ->when($buyerId !== null, fn ($query) => $query->where('buyer_id', $buyerId))
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (Enquiry $enquiry) => [
                'id' => $enquiry->id,
                'buyer_id' => $enquiry->buyer_id,
                'property_id' => $enquiry->property_id,
                'property_title' => $enquiry->property?->title,
                'purchase_request' => (bool) $enquiry->purchase_request,
                'requested_acreage' => $enquiry->requested_acreage,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function linkableIntroductions(?int $propertyId, ?int $buyerId): array
    {
        if ($propertyId === null && $buyerId === null) {
            return [];
        }

        return BrokerIntroduction::query()
            ->when($propertyId !== null, fn ($query) => $query->where('property_id', $propertyId))
            ->when($buyerId !== null, fn ($query) => $query->where('buyer_id', $buyerId))
            ->latest()
            ->limit(10)
            ->get(['id', 'reference', 'buyer_id', 'property_id', 'buyer_name'])
            ->map(fn (BrokerIntroduction $introduction) => [
                'id' => $introduction->id,
                'reference' => $introduction->reference,
                'buyer_id' => $introduction->buyer_id,
                'property_id' => $introduction->property_id,
                'buyer_name' => $introduction->buyer_name,
            ])
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $matchingOffers
     * @param  array<int, array<string, mixed>>  $linkableEnquiries
     * @param  array<int, array<string, mixed>>  $linkableIntroductions
     * @param  array<int, array<string, mixed>>  $saleInstructions
     * @return array<string, mixed>
     */
    private function suggestions(
        ?SalesOrigin $origin,
        ?Property $property,
        array $matchingOffers,
        array $linkableEnquiries,
        array $linkableIntroductions,
        array $saleInstructions,
    ): array {
        $suggestions = [
            'origin' => null,
            'seller_id' => null,
            'enquiry_id' => null,
            'broker_introduction_id' => null,
            'sale_instruction_id' => null,
            'acreage' => null,
            'offer_id' => null,
        ];

        if ($property !== null) {
            $suggestions['acreage'] = $property->remainingAcreage();
            $suggestions['seller_id'] = $property->preferredSellerUserId();
        }

        $primaryOffer = $matchingOffers[0] ?? null;
        if ($primaryOffer !== null) {
            $suggestions['origin'] = SalesOrigin::Offer->value;
            $suggestions['offer_id'] = $primaryOffer['id'];
            if ($primaryOffer['broker_introduction_id'] !== null) {
                $suggestions['broker_introduction_id'] = $primaryOffer['broker_introduction_id'];
                $suggestions['origin'] = SalesOrigin::Introduction->value;
            }
            if ($primaryOffer['requested_acreage'] !== null) {
                $suggestions['acreage'] = (float) $primaryOffer['requested_acreage'];
            }

            return $suggestions;
        }

        $enquiry = $linkableEnquiries[0] ?? null;
        if ($enquiry !== null) {
            $suggestions['origin'] = SalesOrigin::Enquiry->value;
            $suggestions['enquiry_id'] = $enquiry['id'];
            if ($enquiry['requested_acreage'] !== null) {
                $suggestions['acreage'] = (float) $enquiry['requested_acreage'];
            }

            return $suggestions;
        }

        $introduction = $linkableIntroductions[0] ?? null;
        if ($introduction !== null) {
            $suggestions['origin'] = SalesOrigin::Introduction->value;
            $suggestions['broker_introduction_id'] = $introduction['id'];

            return $suggestions;
        }

        $instruction = $saleInstructions[0] ?? null;
        if ($instruction !== null) {
            $suggestions['origin'] = SalesOrigin::Instruction->value;
            $suggestions['sale_instruction_id'] = $instruction['id'];
        }

        if ($origin !== null && $suggestions['origin'] === null) {
            $suggestions['origin'] = $origin->value;
        }

        return $suggestions;
    }

    /**
     * @param  array<int, array<string, mixed>>  $openTransactions
     * @param  array<int, array<string, mixed>>  $matchingOffers
     * @return array<int, string>
     */
    private function alerts(array $openTransactions, array $matchingOffers, ?Property $property, ?User $buyer): array
    {
        $alerts = [];

        if ($property !== null && $buyer !== null && $openTransactions !== []) {
            $alerts[] = 'An open sales case already links this buyer and property. Opening another may duplicate work.';
        }

        foreach ($matchingOffers as $offer) {
            if ($offer['sales_transaction_id'] !== null) {
                $alerts[] = 'Offer #'.$offer['id'].' is already linked to sales case '.$offer['sales_case'].'.';

                break;
            }
        }

        if ($property !== null && $property->remainingAcreage() <= 0) {
            $alerts[] = 'This property has no remaining acreage available for a new transaction.';
        }

        return $alerts;
    }
}
