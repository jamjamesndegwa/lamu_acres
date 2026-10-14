<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['locality_id', 'name', 'slug'])]
class LocalityAlias extends Model
{
    public function locality(): BelongsTo
    {
        return $this->belongsTo(Locality::class);
    }
}
