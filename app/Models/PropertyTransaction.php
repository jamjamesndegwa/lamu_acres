<?php

namespace App\Models;

use App\Enums\ListingPurpose;
use App\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'reference',
    'property_id',
    'status',
    'amount',
    'statement',
    'kind',
    'acreage',
    'starts_on',
    'ends_on',
    'terms',
    'confirmed_by',
    'confirmed_at',
    'ended_at',
])]
class PropertyTransaction extends Model
{
    protected function casts(): array
    {
        return [
            'status' => TransactionStatus::class,
            'amount' => 'integer',
            'kind' => ListingPurpose::class,
            'acreage' => 'decimal:4',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'confirmed_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function isLease(): bool
    {
        return $this->kind === ListingPurpose::Lease;
    }

    public function isActiveLease(): bool
    {
        return $this->isLease() && $this->ended_at === null;
    }
}
