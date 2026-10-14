<?php

namespace App\Services\Buyer;

use App\Models\Property;
use App\Models\SavedProperty;
use App\Models\User;

class SavedPropertyService
{
    public function save(User $buyer, Property $property): SavedProperty
    {
        return SavedProperty::query()->firstOrCreate([
            'user_id' => $buyer->id,
            'property_id' => $property->id,
        ]);
    }

    public function remove(User $buyer, Property $property): void
    {
        SavedProperty::query()
            ->where('user_id', $buyer->id)
            ->where('property_id', $property->id)
            ->delete();
    }
}
