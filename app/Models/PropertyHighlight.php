<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['property_id', 'body', 'position'])]
class PropertyHighlight extends Model
{
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
