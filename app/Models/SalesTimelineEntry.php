<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sales_transaction_id', 'actor_id', 'summary', 'visible_to_buyer'])]
class SalesTimelineEntry extends Model
{
    protected function casts(): array
    {
        return ['visible_to_buyer' => 'boolean'];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(SalesTransaction::class, 'sales_transaction_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
