<?php

namespace App\Models;

use App\Enums\PaymentReconciliation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'commission_agreement_id',
    'amount',
    'reference',
    'status',
    'reconciliation',
    'initiated_by',
    'recorded_by',
    'initiated_at',
    'paid_at',
])]
class CommissionPayment extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'reconciliation' => PaymentReconciliation::class,
            'initiated_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(CommissionAgreement::class, 'commission_agreement_id');
    }
}
