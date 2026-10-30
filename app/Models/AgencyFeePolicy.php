<?php

namespace App\Models;

use App\Enums\AgencyFeePayer;
use App\Enums\FeeCalculationBasis;
use Illuminate\Database\Eloquent\Model;

class AgencyFeePolicy extends Model
{
    public const DEFAULT = 'default';

    public const LEASE_DEFAULT = 'lease_default';

    protected $fillable = [
        'code',
        'name',
        'default_calculation_basis',
        'default_rate_percent',
        'default_fixed_amount',
        'default_monthly_rent_multiplier',
        'default_fee_payer',
        'terms',
        'apply_defaults',
        'effective_from',
    ];

    protected function casts(): array
    {
        return [
            'default_rate_percent' => 'decimal:2',
            'default_fixed_amount' => 'integer',
            'default_monthly_rent_multiplier' => 'decimal:2',
            'apply_defaults' => 'boolean',
            'effective_from' => 'date',
        ];
    }

    public static function standard(): self
    {
        return self::query()->where('code', self::DEFAULT)->firstOrFail();
    }

    public static function leaseStandard(): self
    {
        return self::query()->where('code', self::LEASE_DEFAULT)->firstOrFail();
    }

    public static function forDeal(SalesTransaction $transaction): self
    {
        return $transaction->isLeaseDeal() ? self::leaseStandard() : self::standard();
    }

    public function appliesForProposals(): bool
    {
        if (! $this->apply_defaults) {
            return false;
        }

        if ($this->effective_from === null) {
            return true;
        }

        return $this->effective_from->lte(now()->startOfDay());
    }

    public function calculationBasis(): FeeCalculationBasis
    {
        return FeeCalculationBasis::from($this->default_calculation_basis);
    }

    public function feePayer(): AgencyFeePayer
    {
        return AgencyFeePayer::from($this->default_fee_payer);
    }

    /**
     * @return array<string, mixed>
     */
    public function proposalDefaults(): array
    {
        return [
            'calculation_basis' => $this->default_calculation_basis,
            'rate_percent' => $this->default_rate_percent,
            'fixed_amount' => $this->default_fixed_amount,
            'monthly_rent_multiplier' => $this->default_monthly_rent_multiplier,
            'fee_payer' => $this->default_fee_payer,
        ];
    }
}
