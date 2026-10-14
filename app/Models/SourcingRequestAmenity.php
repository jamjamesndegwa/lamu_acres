<?php

namespace App\Models;

use App\Enums\Amenity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sourcing_request_id', 'amenity', 'required'])]
class SourcingRequestAmenity extends Model
{
    protected function casts(): array
    {
        return [
            'amenity' => Amenity::class,
            'required' => 'boolean',
        ];
    }

    public function sourcingRequest(): BelongsTo
    {
        return $this->belongsTo(SourcingRequest::class);
    }
}
