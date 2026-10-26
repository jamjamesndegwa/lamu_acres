<?php

namespace App\Services\Sales;

use App\Enums\OfferStatus;
use App\Enums\SalesOrigin;
use App\Models\Offer;
use App\Models\Property;
use App\Models\SalesTransaction;
use App\Models\User;
use App\Services\Buyer\InterestWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OfferSalesOrigination
{
    public function __construct(
        private SalesPipeline $pipeline,
        private InterestWorkflow $interestWorkflow,
        private SaleModeTerms $saleModeTerms,
    ) {}

    public function openOrLinkSalesCase(Offer $offer, User $staff): SalesTransaction
    {
        $this->authorizeStaff($staff);

        return DB::transaction(function () use ($offer, $staff): SalesTransaction {
            $offer = Offer::query()->whereKey($offer->id)->lockForUpdate()->firstOrFail();
            $offer->loadMissing(['property', 'buyer']);

            if ($offer->sales_transaction_id !== null) {
                $existing = SalesTransaction::query()->find($offer->sales_transaction_id);
                if ($existing !== null) {
                    return $existing;
                }
            }

            if (! in_array($offer->status, [OfferStatus::Submitted, OfferStatus::UnderReview, OfferStatus::Accepted], true)) {
                throw ValidationException::withMessages([
                    'offer' => 'Only active offers can be opened as sales cases.',
                ]);
            }

            $property = Property::query()->whereKey($offer->property_id)->lockForUpdate()->firstOrFail();
            $acreage = $this->resolvedAcreage($offer, $property);
            $this->saleModeTerms->validateTransactionAcreage($property, $acreage);

            $transaction = $this->findCompatibleOpenCase($offer);

            if ($transaction === null) {
                $payload = [
                    'origin' => $offer->broker_introduction_id !== null
                        ? SalesOrigin::Introduction->value
                        : SalesOrigin::Offer->value,
                    'property_id' => $offer->property_id,
                    'buyer_id' => $offer->buyer_id,
                    'acreage' => $acreage,
                    'assigned_to' => $staff->id,
                ];

                if ($offer->broker_introduction_id !== null) {
                    $payload['broker_introduction_id'] = $offer->broker_introduction_id;
                }

                $transaction = $this->pipeline->open($staff, $payload);
            } else {
                $this->assertCompatibleAttribution($offer, $transaction);
            }

            if (! $transaction->offers()->where('offer_id', $offer->id)->exists()) {
                $this->pipeline->recordOffer($transaction, $staff, (int) $transaction->lock_version, [
                    'offer_id' => $offer->id,
                    'amount' => $offer->amount,
                    'note' => $offer->message,
                ]);
            }

            $offer->sales_transaction_id = $transaction->id;
            $offer->save();

            if ($offer->status === OfferStatus::Submitted) {
                $this->interestWorkflow->transitionOffer($offer->fresh(), OfferStatus::UnderReview, $staff);
            }

            $this->pipeline->timeline(
                $transaction->fresh(),
                $staff,
                'Sales case linked to offer #'.$offer->id.' from '.$offer->buyer->name.'.',
                true,
            );
            $this->pipeline->audit($transaction, $staff, 'offer_linked', [
                'offer_id' => $offer->id,
                'amount' => $offer->amount,
                'requested_acreage' => $offer->requested_acreage,
            ]);

            return $transaction->fresh(['buyer', 'property']);
        });
    }

    public function resolvedAcreage(Offer $offer, Property $property): float
    {
        if ($offer->requested_acreage !== null) {
            return round((float) $offer->requested_acreage, 4);
        }

        return round($property->offeredAcreage(), 4);
    }

    private function findCompatibleOpenCase(Offer $offer): ?SalesTransaction
    {
        $candidates = SalesTransaction::query()
            ->where('property_id', $offer->property_id)
            ->where('buyer_id', $offer->buyer_id)
            ->open()
            ->lockForUpdate()
            ->get();

        foreach ($candidates as $transaction) {
            try {
                $this->assertCompatibleAttribution($offer, $transaction);

                return $transaction;
            } catch (ValidationException) {
                continue;
            }
        }

        return null;
    }

    private function assertCompatibleAttribution(Offer $offer, SalesTransaction $transaction): void
    {
        if ((int) $transaction->buyer_id !== (int) $offer->buyer_id
            || (int) $transaction->property_id !== (int) $offer->property_id) {
            throw ValidationException::withMessages([
                'offer' => 'This sales case belongs to a different buyer or property.',
            ]);
        }

        if ($offer->broker_introduction_id !== null
            && $transaction->broker_introduction_id !== null
            && (int) $transaction->broker_introduction_id !== (int) $offer->broker_introduction_id) {
            throw ValidationException::withMessages([
                'offer' => 'This offer’s broker introduction does not match the open sales case.',
            ]);
        }
    }

    private function authorizeStaff(User $staff): void
    {
        if (! $staff->hasPermission('staff.buyers')
            && ! $staff->hasPermission('staff.sales')
            && ! $staff->hasPermission('staff.properties')) {
            throw ValidationException::withMessages([
                'offer' => 'You are not authorized to open sales cases from offers.',
            ]);
        }
    }
}
