<?php

namespace App\Models;

use App\Enums\SaleInstructionKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['property_id', 'seller_id', 'kind', 'body', 'requested_availability'])]
class SaleInstruction extends Model
{
    protected function casts(): array
    {
        return [
            'kind' => SaleInstructionKind::class,
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
}
