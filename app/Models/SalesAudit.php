<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sales_transaction_id', 'commission_agreement_id', 'actor_id', 'event', 'payload'])]
class SalesAudit extends Model
{
    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(SalesTransaction::class, 'sales_transaction_id');
    }

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(CommissionAgreement::class, 'commission_agreement_id');
    }
}
