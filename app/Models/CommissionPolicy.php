<?php

namespace App\Models;

use App\Enums\CommissionFunding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class CommissionPolicy extends Model
{
    public const DEFAULT = 'default';

    protected $fillable = [
        'code',
        'name',
        'default_rate_percent',
        'default_fixed_amount',
        'default_funding_party',
        'default_payment_due_days',
        'default_protection_months',
        'terms',
        'apply_defaults',
    ];

    protected function casts(): array
    {
        return [
            'default_rate_percent' => 'decimal:2',
            'default_fixed_amount' => 'integer',
            'default_payment_due_days' => 'integer',
            'default_protection_months' => 'integer',
            'apply_defaults' => 'boolean',
        ];
    }

    public static function standard(): self
    {
        return self::query()->where('code', self::DEFAULT)->firstOrFail();
    }

    public function fundingParty(): CommissionFunding
    {
        return CommissionFunding::from($this->default_funding_party);
    }

    public function defaultProtectionEndsAt(): Carbon
    {
        return now()->addMonths(max(1, (int) $this->default_protection_months));
    }

    /**
     * @return array<string, mixed>
     */
    public function proposalDefaults(): array
    {
        return [
            'rate_percent' => $this->default_rate_percent,
            'fixed_amount' => $this->default_fixed_amount,
            'funding_party' => $this->default_funding_party,
            'payment_due_days' => $this->default_payment_due_days,
            'protection_ends_at' => $this->defaultProtectionEndsAt()->toDateString(),
        ];
    }

    public function summaryLabel(): string
    {
        $parts = [];

        if ($this->default_rate_percent !== null) {
            $parts[] = number_format((float) $this->default_rate_percent, 2).' % rate';
        }

        if ($this->default_fixed_amount !== null) {
            $parts[] = 'KSh '.number_format((int) $this->default_fixed_amount).' fixed';
        }

        if ($parts === []) {
            $parts[] = 'No default rate or fixed amount';
        }

        $parts[] = $this->fundingParty()->label().' funds';
        $parts[] = (int) $this->default_payment_due_days.' day payment term';
        $parts[] = (int) $this->default_protection_months.' month protection';

        return implode(' · ', $parts);
    }
}
