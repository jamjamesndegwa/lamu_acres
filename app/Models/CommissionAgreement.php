<?php

namespace App\Models;

use App\Enums\CommissionFunding;
use App\Enums\CommissionStatus;
use App\Enums\QualifyingEvent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'reference',
    'sales_transaction_id',
    'broker_id',
    'buyer_id',
    'property_id',
    'rate_percent',
    'fixed_amount',
    'earned_amount',
    'funding_party',
    'protection_starts_at',
    'protection_ends_at',
    'qualifying_event',
    'payment_due_days',
    'status',
    'dispute_reason',
    'version',
    'previous_id',
    'root_id',
    'open_claim',
    'accepted_at',
    'accepted_by',
])]
class CommissionAgreement extends Model
{
    protected function casts(): array
    {
        return [
            'rate_percent' => 'decimal:2',
            'fixed_amount' => 'integer',
            'earned_amount' => 'integer',
            'funding_party' => CommissionFunding::class,
            'protection_starts_at' => 'datetime',
            'protection_ends_at' => 'datetime',
            'qualifying_event' => QualifyingEvent::class,
            'status' => CommissionStatus::class,
            'accepted_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(SalesTransaction::class, 'sales_transaction_id');
    }

    public function broker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'broker_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(CommissionPayment::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(self::class, 'root_id');
    }
}
