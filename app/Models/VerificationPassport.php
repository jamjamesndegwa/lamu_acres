<?php

namespace App\Models;

use App\Enums\VerificationLevel;
use App\Enums\VerificationOverride;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['property_id', 'level', 'override', 'critical_exception', 'suspended_at', 'assigned_to', 'assigned_by', 'deadline_at'])]
class VerificationPassport extends Model
{
    protected function casts(): array
    {
        return [
            'level' => VerificationLevel::class,
            'override' => VerificationOverride::class,
            'suspended_at' => 'datetime',
            'deadline_at' => 'datetime',
        ];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
