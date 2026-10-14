<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionInvoice extends Model
{
    protected $fillable = [
        'subscription_id',
        'number',
        'amount',
        'currency',
        'status',
        'policy',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class, 'invoice_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(SubscriptionReceipt::class, 'invoice_id');
    }
}
