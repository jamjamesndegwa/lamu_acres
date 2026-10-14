<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    public const INVESTOR_PASS = 'investor_pass';

    protected $fillable = [
        'code',
        'name',
        'amount_cents',
        'currency',
        'interval',
        'terms',
        'is_active',
        'checkout_amount',
        'checkout_currency',
        'checkout_policy',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'checkout_amount' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public static function investorPass(): self
    {
        return self::query()->where('code', self::INVESTOR_PASS)->firstOrFail();
    }

    public function amountLabel(): string
    {
        return $this->currency.' '.number_format($this->amount_cents / 100, 2).' / '.$this->interval;
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
