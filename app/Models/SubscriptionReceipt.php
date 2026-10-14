<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionReceipt extends Model
{
    protected $fillable = [
        'payment_id',
        'invoice_id',
        'provider_receipt',
        'amount',
        'currency',
        'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'issued_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPayment::class, 'payment_id');
    }
}
