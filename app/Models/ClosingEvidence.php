<?php

namespace App\Models;

use App\Enums\ClosingEvidenceState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sales_transaction_id', 'code', 'state', 'note', 'reviewed_by'])]
class ClosingEvidence extends Model
{
    protected $table = 'closing_evidence';

    protected function casts(): array
    {
        return ['state' => ClosingEvidenceState::class];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(SalesTransaction::class, 'sales_transaction_id');
    }
}
