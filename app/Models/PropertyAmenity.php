<?php

namespace App\Models;

use App\Enums\Amenity;
use App\Enums\AmenityState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['property_id', 'amenity', 'state'])]
class PropertyAmenity extends Model
{
    protected function casts(): array
    {
        return [
            'amenity' => Amenity::class,
            'state' => AmenityState::class,
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
