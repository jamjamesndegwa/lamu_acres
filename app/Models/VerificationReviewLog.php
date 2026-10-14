<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['property_id', 'verification_item_id', 'actor_id', 'decision', 'reason'])]
class VerificationReviewLog extends Model
{
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(VerificationItem::class, 'verification_item_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
