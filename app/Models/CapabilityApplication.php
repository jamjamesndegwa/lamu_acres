<?php

namespace App\Models;

use App\Enums\CapabilityApplicationStatus;
use App\Enums\PartnerCapability;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'capability',
    'status',
    'organization',
    'statement',
    'decided_by',
    'decided_at',
    'decision_reason',
])]
class CapabilityApplication extends Model
{
    protected function casts(): array
    {
        return [
            'capability' => PartnerCapability::class,
            'status' => CapabilityApplicationStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
