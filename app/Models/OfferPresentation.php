<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['offer_id', 'property_id', 'presented_by', 'amount', 'summary', 'seller_response', 'responded_at'])]
class OfferPresentation extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'responded_at' => 'datetime',
        ];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
