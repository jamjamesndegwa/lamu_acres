<?php

namespace App\Models;

use App\Enums\AgencyFeePayer;
use App\Enums\CommissionStatus;
use App\Enums\FeeCalculationBasis;
use App\Enums\QualifyingEvent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'reference',
    'sales_transaction_id',
    'property_id',
    'seller_id',
    'fee_payer',
    'calculation_basis',
    'rate_percent',
    'monthly_rent_multiplier',
    'fixed_amount',
    'earned_amount',
    'calculation_snapshot',
    'collected_amount',
    'qualifying_event',
    'effective_from',
    'effective_until',
    'terms',
    'status',
    'dispute_reason',
    'version',
    'previous_id',
    'root_id',
    'open_claim',
    'accepted_at',
    'accepted_by',
])]
class AgencyFeeAgreement extends Model
{
    protected function casts(): array
    {
        return [
            'fee_payer' => AgencyFeePayer::class,
            'calculation_basis' => FeeCalculationBasis::class,
            'rate_percent' => 'decimal:2',
            'monthly_rent_multiplier' => 'decimal:2',
            'fixed_amount' => 'integer',
            'earned_amount' => 'integer',
            'calculation_snapshot' => 'array',
            'collected_amount' => 'integer',
            'qualifying_event' => QualifyingEvent::class,
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
            'status' => CommissionStatus::class,
            'accepted_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(SalesTransaction::class, 'sales_transaction_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(AgencyFeeReceipt::class);
    }

    public function outstandingReceivable(): int
    {
        $earned = (int) ($this->earned_amount ?? 0);
        $collected = (int) $this->collected_amount;

        return max(0, $earned - $collected);
    }
}
