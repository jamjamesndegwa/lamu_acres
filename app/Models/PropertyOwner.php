<?php

namespace App\Models;

use App\Enums\OwnerLinkStatus;
use App\Enums\OwnerRelationship;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['property_id', 'user_id', 'relationship', 'status', 'share_percent', 'verified_at', 'verified_by'])]
class PropertyOwner extends Model
{
    protected function casts(): array
    {
        return [
            'relationship' => OwnerRelationship::class,
            'status' => OwnerLinkStatus::class,
            'share_percent' => 'decimal:2',
            'verified_at' => 'datetime',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
