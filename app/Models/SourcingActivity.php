<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sourcing_request_id', 'actor_id', 'summary', 'visible_to_buyer'])]
class SourcingActivity extends Model
{
    protected function casts(): array
    {
        return [
            'visible_to_buyer' => 'boolean',
        ];
    }

    public function sourcingRequest(): BelongsTo
    {
        return $this->belongsTo(SourcingRequest::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
