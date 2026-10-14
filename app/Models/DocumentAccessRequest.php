<?php

namespace App\Models;

use App\Enums\DocumentAccessStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'buyer_id',
    'property_id',
    'property_match_id',
    'approved_by',
    'status',
    'note',
])]
class DocumentAccessRequest extends Model
{
    protected function casts(): array
    {
        return [
            'status' => DocumentAccessStatus::class,
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(PropertyMatch::class, 'property_match_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isOwnedBy(User $user): bool
    {
        return (int) $this->buyer_id === (int) $user->id;
    }
}
