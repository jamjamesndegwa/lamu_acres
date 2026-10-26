<?php

namespace App\Services\Seller;

use App\Enums\OwnerLinkStatus;
use App\Enums\OwnerRelationship;
use App\Enums\PropertyStatus;
use App\Enums\SalesStage;
use App\Models\Property;
use App\Models\SalesTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SellerSalesHistory
{
    /**
     * @return Builder<SalesTransaction>
     */
    public function completedSalesQuery(User $seller): Builder
    {
        $propertyIds = $this->propertyIds($seller);

        return SalesTransaction::query()
            ->where('stage', SalesStage::Completed)
            ->whereIn('property_id', $propertyIds)
            ->with(['property', 'buyer:id,name']);
    }

    /**
     * @return Collection<int, int>
     */
    public function propertyIds(User $seller): Collection
    {
        return Property::query()
            ->whereNull('parent_id')
            ->whereHas('owners', fn ($query) => $query
                ->where('user_id', $seller->id)
                ->where('status', OwnerLinkStatus::Verified)
                ->whereIn('relationship', OwnerRelationship::sellerFacing()))
            ->pluck('id');
    }

    public function canView(User $seller, SalesTransaction $transaction): bool
    {
        return $this->completedSalesQuery($seller)->whereKey($transaction->id)->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function presentCompletedSale(SalesTransaction $transaction): array
    {
        $property = $transaction->property;
        $inventory = $property->inventorySnapshot();
        $soldPortion = $transaction->soldInventoryRecord
            ?? $property->portions()->where('sales_transaction_id', $transaction->id)->first();

        return [
            'reference' => $transaction->reference,
            'property' => $property,
            'buyer_name' => $transaction->buyer?->name,
            'agreed_price' => $transaction->agreed_price,
            'acreage_sold' => (float) $transaction->acreage,
            'completed_at' => $transaction->completed_at,
            'inventory' => $inventory,
            'sold_portion' => $soldPortion,
        ];
    }
}
