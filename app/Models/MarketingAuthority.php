<?php

namespace App\Models;

use App\Enums\AuthorityStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['property_id', 'seller_id', 'status', 'terms', 'submitted_at', 'decided_at', 'decided_by'])]
class MarketingAuthority extends Model
{
    protected function casts(): array
    {
        return [
            'status' => AuthorityStatus::class,
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
}
