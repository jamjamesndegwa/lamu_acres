<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['property_id', 'actor_id', 'status', 'availability'])]
class PropertyAvailabilityEvent extends Model
{
    public const UPDATED_AT = null;

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
