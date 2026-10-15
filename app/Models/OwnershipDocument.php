<?php

namespace App\Models;

use App\Enums\OwnershipDocumentType;
use App\Enums\VerificationItemState;
use App\Models\Concerns\StoresFileUrls;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'property_id',
    'user_id',
    'original_name',
    'path',
    'disk',
    'mime',
    'size_bytes',
    'document_type',
    'state',
    'reviewed_by',
    'reviewed_at',
    'review_note',
    'expires_at',
])]
class OwnershipDocument extends Model
{
    use StoresFileUrls;

    protected $hidden = [
        'url',
        'path',
        'disk',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => OwnershipDocumentType::class,
            'state' => VerificationItemState::class,
            'reviewed_at' => 'datetime',
            'expires_at' => 'date',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function hasFile(): bool
    {
        return filled($this->path);
    }
}
