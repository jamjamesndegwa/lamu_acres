<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SubscriptionPayment extends Model
{
    protected $fillable = [
        'subscription_id',
        'invoice_id',
        'user_id',
        'idempotency_key',
        'phone',
        'amount',
        'currency',
        'status',
        'merchant_request_id',
        'checkout_request_id',
        'provider_result_code',
        'provider_result_desc',
        'attempted_at',
        'expires_at',
        'reconciled_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => PaymentStatus::class,
            'attempted_at' => 'datetime',
            'expires_at' => 'datetime',
            'reconciled_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SubscriptionInvoice::class, 'invoice_id');
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(SubscriptionReceipt::class, 'payment_id');
    }
}
