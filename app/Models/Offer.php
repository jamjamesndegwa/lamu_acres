<?php

namespace App\Models;

use App\Enums\OfferStatus;
use Database\Factories\OfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'buyer_id',
    'property_id',
    'property_match_id',
    'enquiry_id',
    'broker_introduction_id',
    'submitted_by_broker_id',
    'sales_transaction_id',
    'amount',
    'requested_acreage',
    'currency',
    'message',
    'status',
])]
class Offer extends Model
{
    /** @use HasFactory<OfferFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'requested_acreage' => 'decimal:4',
            'status' => OfferStatus::class,
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(PropertyMatch::class, 'property_match_id');
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    public function brokerIntroduction(): BelongsTo
    {
        return $this->belongsTo(BrokerIntroduction::class);
    }

    public function submittedByBroker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_broker_id');
    }

    public function salesTransaction(): BelongsTo
    {
        return $this->belongsTo(SalesTransaction::class);
    }

    public function presentations(): HasMany
    {
        return $this->hasMany(OfferPresentation::class);
    }

    public function isOwnedBy(User $user): bool
    {
        return (int) $this->buyer_id === (int) $user->id;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [OfferStatus::Submitted, OfferStatus::UnderReview], true);
    }

    public function scopeVisibleToStaff(Builder $query, User $user): Builder
    {
        if ($user->hasPermission('staff.buyers')
            || $user->hasPermission('staff.sales')
            || $user->hasPermission('staff.properties')) {
            return $query;
        }

        return $query->whereRaw('0 = 1');
    }
}
