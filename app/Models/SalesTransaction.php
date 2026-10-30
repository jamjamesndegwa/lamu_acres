<?php

namespace App\Models;

use App\Enums\DealKind;
use App\Enums\RentFrequency;
use App\Enums\SalesOrigin;
use App\Enums\SalesOutcome;
use App\Enums\SalesStage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'reference',
    'deal_kind',
    'property_id',
    'buyer_id',
    'origin',
    'enquiry_id',
    'sourcing_request_id',
    'broker_introduction_id',
    'sale_instruction_id',
    'assigned_to',
    'stage',
    'outcome',
    'agreed_price',
    'agreed_rent',
    'lease_premium',
    'acreage',
    'currency',
    'lease_starts_on',
    'lease_ends_on',
    'rent_frequency',
    'property_transaction_id',
    'lock_version',
    'opened_at',
    'completed_at',
    'closed_at',
])]
class SalesTransaction extends Model
{
    protected function casts(): array
    {
        return [
            'deal_kind' => DealKind::class,
            'origin' => SalesOrigin::class,
            'stage' => SalesStage::class,
            'outcome' => SalesOutcome::class,
            'agreed_price' => 'integer',
            'agreed_rent' => 'integer',
            'lease_premium' => 'integer',
            'acreage' => 'decimal:4',
            'rent_frequency' => RentFrequency::class,
            'lease_starts_on' => 'date',
            'lease_ends_on' => 'date',
            'lock_version' => 'integer',
            'opened_at' => 'datetime',
            'completed_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function introduction(): BelongsTo
    {
        return $this->belongsTo(BrokerIntroduction::class, 'broker_introduction_id');
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    public function saleInstruction(): BelongsTo
    {
        return $this->belongsTo(SaleInstruction::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function soldInventoryRecord(): HasOne
    {
        return $this->hasOne(Property::class, 'sales_transaction_id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(SalesOfferEntry::class)->latest();
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(SalesMilestone::class)->latest();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SalesDocumentRef::class)->latest();
    }

    public function timeline(): HasMany
    {
        return $this->hasMany(SalesTimelineEntry::class)->latest();
    }

    public function closingReviews(): HasMany
    {
        return $this->hasMany(ClosingReview::class)->latest();
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(ClosingEvidence::class);
    }

    public function agreements(): HasMany
    {
        return $this->hasMany(CommissionAgreement::class);
    }

    public function agencyFeeAgreements(): HasMany
    {
        return $this->hasMany(AgencyFeeAgreement::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(SalesAudit::class)->latest();
    }

    public function propertyTransaction(): BelongsTo
    {
        return $this->belongsTo(PropertyTransaction::class, 'property_transaction_id');
    }

    public function dealKind(): DealKind
    {
        return $this->deal_kind ?? DealKind::Sale;
    }

    public function isLeaseDeal(): bool
    {
        return $this->dealKind() === DealKind::Lease;
    }

    public function isOpen(): bool
    {
        return $this->stage !== SalesStage::Completed
            && ($this->outcome === null || $this->outcome === SalesOutcome::Disputed);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query
            ->where('stage', '!=', SalesStage::Completed)
            ->where(function (Builder $query): void {
                $query->whereNull('outcome')
                    ->orWhere('outcome', SalesOutcome::Disputed);
            });
    }
}
