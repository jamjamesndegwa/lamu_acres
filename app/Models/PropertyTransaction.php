<?php

namespace App\Models;

use App\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['reference', 'property_id', 'status', 'amount', 'statement'])]
class PropertyTransaction extends Model
{
    protected function casts(): array
    {
        return [
            'status' => TransactionStatus::class,
            'amount' => 'integer',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
