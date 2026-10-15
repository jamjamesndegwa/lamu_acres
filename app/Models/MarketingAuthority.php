<?php

namespace App\Models;

use App\Enums\AuthorityScope;
use App\Enums\AuthorityStatus;
use App\Enums\ListingPurpose;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['property_id', 'seller_id', 'status', 'terms', 'scope', 'valid_until', 'submitted_at', 'decided_at', 'decided_by'])]
class MarketingAuthority extends Model
{
    protected function casts(): array
    {
        return [
            'status' => AuthorityStatus::class,
            'scope' => AuthorityScope::class,
            'valid_until' => 'date',
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

    public function isExpired(): bool
    {
        return $this->valid_until !== null && $this->valid_until->endOfDay()->isPast();
    }

    public function permitsMarketing(ListingPurpose $purpose): bool
    {
        return $this->status === AuthorityStatus::Active
            && ! $this->isExpired()
            && ($this->scope ?? AuthorityScope::Both)->covers($purpose);
    }
}
