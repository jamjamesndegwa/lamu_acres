<?php

namespace App\Models;

use App\Enums\VerificationCategory;
use App\Enums\VerificationItemState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'property_id',
    'category',
    'state',
    'reviewer_id',
    'evidence_note',
    'checked_at',
    'expires_at',
    'review_due_at',
    'reminded_at',
    'notes',
])]
class VerificationItem extends Model
{
    protected function casts(): array
    {
        return [
            'category' => VerificationCategory::class,
            'state' => VerificationItemState::class,
            'checked_at' => 'datetime',
            'expires_at' => 'datetime',
            'review_due_at' => 'datetime',
            'reminded_at' => 'datetime',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(EvidenceFile::class);
    }
}
