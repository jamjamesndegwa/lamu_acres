<?php

namespace App\Models;

use App\Enums\MatchBand;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sourcing_request_id', 'property_id', 'band', 'distance_km', 'reasons'])]
class SourcingMatch extends Model
{
    protected function casts(): array
    {
        return [
            'band' => MatchBand::class,
            'distance_km' => 'decimal:3',
            'reasons' => 'array',
        ];
    }

    public function sourcingRequest(): BelongsTo
    {
        return $this->belongsTo(SourcingRequest::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
