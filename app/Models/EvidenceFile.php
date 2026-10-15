<?php

namespace App\Models;

use App\Enums\EvidenceSensitivity;
use App\Models\Concerns\StoresFileUrls;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['property_id', 'verification_item_id', 'uploaded_by', 'sensitivity', 'original_name', 'path', 'disk', 'mime', 'size_bytes'])]
class EvidenceFile extends Model
{
    use StoresFileUrls;

    protected function casts(): array
    {
        return [
            'sensitivity' => EvidenceSensitivity::class,
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(VerificationItem::class, 'verification_item_id');
    }

    public function grants(): HasMany
    {
        return $this->hasMany(EvidenceGrant::class);
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(EvidenceAccessLog::class);
    }
}
